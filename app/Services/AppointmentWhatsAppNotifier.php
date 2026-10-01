<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

/**
 * Costruisce e invia (via WAHA) i messaggi WhatsApp legati a un appuntamento.
 * Per ora solo la conferma; i reminder verranno aggiunti qui.
 */
class AppointmentWhatsAppNotifier
{
    /** Il database è in UTC: data e ora nel messaggio sono sempre in ora italiana. */
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(private readonly WahaService $waha)
    {
    }

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

        if ($result->success) {
            // Il messaggio è stato accettato da WhatsApp (non significa ancora "consegnato"). Senza eventi: non rilancia l'observer.
            $appointment->updateQuietly(['whatsapp_sent' => true]);
        } else {
            Log::warning('Conferma WhatsApp automatica non inviata.', [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
                'error' => $result->error,
            ]);
        }

        return $result;
    }

    public function confirmationMessage(Appointment $appointment): string
    {
        $when = $appointment->scheduled_at->copy()->setTimezone(self::TIMEZONE)->locale('it');

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
            'Confermiamo il tuo appuntamento ✅',
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
}
