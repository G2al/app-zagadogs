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
        $scheduledAt = $appointment->scheduled_at?->copy()->timezone(config('app.timezone'));
        $date = $scheduledAt?->format('d/m/Y') ?? '';
        $time = $scheduledAt?->format('H:i') ?? '';
        $whenText = '';

        if ($scheduledAt) {
            if ($scheduledAt->isToday()) {
                $whenText = "oggi alle {$time}";
            } elseif ($scheduledAt->isTomorrow()) {
                $whenText = "domani alle {$time}";
            } else {
                $whenText = "il {$date} alle {$time}";
            }
        }

        $message = "Ciao {$clientName},\n" .
            "confermiamo l'appuntamento per {$dogName}\n" .
            "{$whenText}.\n" .
            "ZagaDogs";

        $phone = preg_replace('/\D+/', '', (string) ($appointment->client?->phone ?? ''));

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}
