<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\AppointmentWhatsAppNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unico punto in cui si intercetta la conferma di un appuntamento, qualunque sia l'origine
 * (API, calendario Filament, azione "Programma", form di modifica).
 */
class AppointmentObserver
{
    /** Creato direttamente come "confermato" (es. nuovo appuntamento con data dal calendario o dall'API). */
    public function created(Appointment $appointment): void
    {
        if ($appointment->status === 'confirmed') {
            $this->planReminder($appointment);
            $this->sendConfirmationAfterResponse($appointment);
        }
    }

    public function updated(Appointment $appointment): void
    {
        if ($appointment->status !== 'confirmed') {
            return;
        }

        // Solo se lo stato è cambiato adesso: salvare un appuntamento già confermato non reinvia la conferma.
        if ($appointment->wasChanged('status')) {
            $this->sendConfirmationAfterResponse($appointment);
        }

        // Confermato adesso oppure spostato: il promemoria va (ri)pianificato.
        if ($appointment->wasChanged(['status', 'scheduled_at'])) {
            $this->planReminder($appointment);
        }
    }

    /** Scrive solo una riga nel database: non può far fallire il salvataggio dell'appuntamento. */
    private function planReminder(Appointment $appointment): void
    {
        try {
            app(AppointmentWhatsAppNotifier::class)->planReminder($appointment);
        } catch (Throwable $e) {
            Log::error('Errore nella pianificazione del promemoria WhatsApp.', [
                'appointment_id' => $appointment->getKey(),
                'exception' => $e::class,
                'detail' => $e->getMessage(),
            ]);
        }
    }

    /**
     * L'invio è rimandato a dopo la risposta HTTP: a quel punto i servizi (collegati dopo il salvataggio)
     * sono già sul database, l'utente non aspetta WAHA e un errore non può far fallire la conferma.
     * Il nome evita doppioni se lo stesso appuntamento viene salvato più volte nella stessa richiesta.
     */
    private function sendConfirmationAfterResponse(Appointment $appointment): void
    {
        $id = $appointment->getKey();

        defer(function () use ($id): void {
            try {
                app(AppointmentWhatsAppNotifier::class)->sendConfirmation($id);
            } catch (Throwable $e) {
                Log::error('Errore imprevisto nella conferma WhatsApp automatica.', [
                    'appointment_id' => $id,
                    'exception' => $e::class,
                    'detail' => $e->getMessage(),
                ]);
            }
        }, "appointment-confirmation-{$id}");
    }
}
