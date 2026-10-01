<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

/**
 * Costruisce e invia (via WAHA) i messaggi WhatsApp legati a un appuntamento: conferma e promemoria.
 * Ogni invio viene registrato in whatsapp_messages.
 */
class AppointmentWhatsAppNotifier
{
    /** Tentativi massimi per un promemoria prima di darlo per fallito (uno ogni 5 minuti). */
    public const MAX_REMINDER_ATTEMPTS = 6;

    public function __construct(private readonly WahaService $waha)
    {
    }

    // ---------------------------------------------------------------- conferma

    /**
     * Rilegge l'appuntamento dal database (così trova anche i servizi collegati dopo il salvataggio)
     * e invia la conferma solo se è davvero confermato e ha una data.
     */
    public function sendConfirmation(int $appointmentId): ?WahaResult
    {
        $appointment = Appointment::with(['client', 'services'])->find($appointmentId);

        if (! $appointment || $appointment->status !== 'confirmed') {
            return null;
        }

        if (! $appointment->scheduled_at) {
            Log::info('Conferma WhatsApp non inviata: appuntamento confermato senza data.', ['appointment_id' => $appointment->id]);

            return null;
        }

        $result = $this->waha->sendText(
            (string) ($appointment->client?->phone ?? ''),
            $this->confirmationMessage($appointment),
        );

        $message = $this->record($appointment, WhatsAppMessage::CONFIRMATION, $result);

        if ($result->success) {
            // Accettato da WhatsApp (non significa ancora "consegnato"). Senza eventi: non rilancia l'observer.
            $appointment->updateQuietly(['whatsapp_sent' => true]);
        } else {
            Log::warning('Conferma WhatsApp automatica non inviata.', [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
                'error' => $result->error,
                'attempts' => $message->attempts,
            ]);
        }

        return $result;
    }

    public function confirmationMessage(Appointment $appointment): string
    {
        return $this->message($appointment, 'Confermiamo il tuo appuntamento ✅');
    }

    // -------------------------------------------------------------- promemoria

    /**
     * Pianifica il promemoria di un appuntamento confermato (chiamata quando viene confermato o spostato).
     * Se quel promemoria è già stato inviato per lo stesso orario non fa niente.
     */
    public function planReminder(Appointment $appointment): void
    {
        if ($appointment->status !== 'confirmed' || ! $appointment->scheduled_at) {
            return;
        }

        $due = ReminderPlanner::dueAt($appointment->scheduled_at, now());

        $reminder = WhatsAppMessage::firstOrNew([
            'appointment_id' => $appointment->id,
            'type' => WhatsAppMessage::REMINDER,
            'for_scheduled_at' => $appointment->scheduled_at,
        ]);

        if ($reminder->status === WhatsAppMessage::SENT) {
            return;
        }

        if ($due === null) {
            if ($reminder->exists) {
                $reminder->update(['status' => WhatsAppMessage::SKIPPED, 'due_at' => null]);
            }

            return;
        }

        $reminder->fill([
            'status' => WhatsAppMessage::PENDING,
            'due_at' => $due,
            'attempts' => 0,
            'last_error' => null,
        ])->save();
    }

    /**
     * Invia i promemoria arrivati a scadenza. Chiamata dal comando schedulato.
     *
     * @return array{sent: int, retry: int, failed: int, skipped: int}
     */
    public function sendDueReminders(): array
    {
        $counts = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];

        $due = WhatsAppMessage::query()
            ->where('type', WhatsAppMessage::REMINDER)
            ->where('status', WhatsAppMessage::PENDING)
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->get();

        foreach ($due as $reminder) {
            $outcome = $this->sendReminder($reminder);
            $counts[$outcome]++;
        }

        return $counts;
    }

    /** @return 'sent'|'retry'|'failed'|'skipped' */
    private function sendReminder(WhatsAppMessage $reminder): string
    {
        $appointment = Appointment::with(['client', 'services'])->find($reminder->appointment_id);

        // Annullato, tornato in attesa, spostato o già passato: il promemoria non ha più senso.
        if (
            ! $appointment
            || $appointment->status !== 'confirmed'
            || ! $appointment->scheduled_at
            || ! $appointment->scheduled_at->equalTo($reminder->for_scheduled_at)
            || $appointment->scheduled_at->isPast()
        ) {
            $reminder->update(['status' => WhatsAppMessage::SKIPPED]);

            return 'skipped';
        }

        try {
            $result = $this->waha->sendText(
                (string) ($appointment->client?->phone ?? ''),
                $this->reminderMessage($appointment),
            );
        } catch (\Throwable $e) {
            $result = WahaResult::failed(WahaResult::HTTP_ERROR, $e->getMessage());
        }

        $reminder = $this->record($appointment, WhatsAppMessage::REMINDER, $result);

        if ($result->success) {
            return 'sent';
        }

        // Numero non valido o WAHA non configurato non si risolvono riprovando.
        $final = $reminder->attempts >= self::MAX_REMINDER_ATTEMPTS
            || in_array($result->error, [WahaResult::INVALID_PHONE, WahaResult::NOT_CONFIGURED], true);

        if ($final) {
            $reminder->update(['status' => WhatsAppMessage::FAILED]);
            Log::error('Promemoria WhatsApp non inviato, rinuncio.', [
                'appointment_id' => $appointment->id,
                'error' => $result->error,
                'attempts' => $reminder->attempts,
            ]);

            return 'failed';
        }

        return 'retry';
    }

    public function reminderMessage(Appointment $appointment): string
    {
        $when = $appointment->scheduled_at->copy()->setTimezone(ReminderPlanner::TIMEZONE);

        $day = match (true) {
            $when->isToday() => ' di oggi',
            $when->isTomorrow() => ' di domani',
            default => '',
        };

        return $this->message($appointment, "Ti ricordiamo il tuo appuntamento{$day} ⏰");
    }

    // ----------------------------------------------------------------- interni

    private function message(Appointment $appointment, string $headline): string
    {
        $when = $appointment->scheduled_at->copy()->setTimezone(ReminderPlanner::TIMEZONE)->locale('it');

        $services = $appointment->services
            ->pluck('name')
            ->map(fn (?string $name) => trim((string) $name))
            ->filter()
            ->implode(', ');

        $name = trim((string) ($appointment->client?->first_name ?? ''));
        if ($name === '') {
            $name = trim((string) ($appointment->client?->last_name ?? ''));
        }

        $lines = [
            $name !== '' ? "Ciao {$name} 👋" : 'Ciao 👋',
            $headline,
            '',
            '📅 ' . $when->translatedFormat('l j F Y'),
            '🕐 ' . $when->format('H:i'),
        ];

        if ($services !== '') {
            $lines[] = '✂️ ' . $services;
        }

        array_push($lines, '', 'A presto!');

        return implode("\n", $lines);
    }

    /** Registra l'esito di un tentativo (una riga per appuntamento, tipo e orario). */
    private function record(Appointment $appointment, string $type, WahaResult $result): WhatsAppMessage
    {
        $message = WhatsAppMessage::firstOrNew([
            'appointment_id' => $appointment->id,
            'type' => $type,
            'for_scheduled_at' => $appointment->scheduled_at,
        ]);

        $message->fill([
            'status' => $result->success ? WhatsAppMessage::SENT : ($message->status === WhatsAppMessage::SENT ? WhatsAppMessage::SENT : WhatsAppMessage::PENDING),
            'attempts' => ($message->attempts ?? 0) + 1,
            'last_error' => $result->success ? null : mb_substr("{$result->error}: {$result->message}", 0, 250),
            'sent_at' => $result->success ? now() : $message->sent_at,
        ]);

        // La conferma non viene ritentata: se fallisce resta "failed". Il promemoria resta "pending" finché non si rinuncia.
        if (! $result->success && $type === WhatsAppMessage::CONFIRMATION) {
            $message->status = WhatsAppMessage::FAILED;
        }

        $message->save();

        return $message;
    }
}
