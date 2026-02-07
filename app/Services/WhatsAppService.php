<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Carbon;

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
        $whenText = $this->formatWhenText($appointment->scheduled_at);

        $message = "Ciao {$clientName},\n" .
            "confermiamo l'appuntamento per {$dogName}\n" .
            "{$whenText}.\n" .
            "ZagaDogs";

        $phone = preg_replace('/\D+/', '', (string) ($appointment->client?->phone ?? ''));

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    public function sendAppointmentReminder(Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'dog']);

        $clientName = trim((string) ($appointment->client?->first_name ?? ''));
        if ($clientName === '') {
            $clientName = trim((string) ($appointment->client?->last_name ?? ''));
        }

        $dogName = trim((string) ($appointment->dog?->name ?? ''));
        $whenText = $this->formatWhenText($appointment->scheduled_at);

        $message = "Ciao {$clientName},\n" .
            "ti ricordiamo l'appuntamento per {$dogName}\n" .
            "{$whenText}.\n" .
            "ZagaDogs";

        $phone = preg_replace('/\D+/', '', (string) ($appointment->client?->phone ?? ''));

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function formatWhenText(?Carbon $scheduledAt): string
    {
        if (! $scheduledAt) {
            return '';
        }

        $scheduledAt = $scheduledAt->copy()->timezone(config('app.timezone'));
        $time = $scheduledAt->format('H:i');

        if ($scheduledAt->isToday()) {
            return "oggi alle {$time}";
        }

        if ($scheduledAt->isTomorrow()) {
            return "domani alle {$time}";
        }

        return 'il ' . $scheduledAt->format('d/m/Y') . " alle {$time}";
    }
}
