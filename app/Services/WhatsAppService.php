<?php

namespace App\Services;

use App\Models\Appointment;

class WhatsAppService
{
    public function sendAppointmentConfirmation(Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'dog']);

        $clientName = trim((string) ($appointment->client?->first_name ?? ''));
        if ($clientName === '') {
            $clientName = trim((string) ($appointment->client?->last_name ?? ''));
        }

        $dogName = trim((string) ($appointment->dog?->name ?? ''));
        $date = $appointment->scheduled_at?->format('d/m/Y') ?? '';
        $time = $appointment->scheduled_at?->format('H:i') ?? '';

        $message = "Ciao {$clientName},\n" .
            "confermiamo l'appuntamento per {$dogName}\n" .
            "il {$date} alle {$time}.\n" .
            "ZagaDogs ??";

        $phone = preg_replace('/\D+/', '', (string) ($appointment->client?->phone ?? ''));

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}