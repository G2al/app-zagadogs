<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Carbon;

class WhatsAppService
{
    public function sendAppointmentConfirmation(Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'dog', 'services']);

        $clientName = trim((string) ($appointment->client?->first_name ?? ''));
        if ($clientName === '') {
            $clientName = trim((string) ($appointment->client?->last_name ?? ''));
        }

        $dogName = trim((string) ($appointment->dog?->name ?? ''));
        $whenText = $this->formatWhenText($appointment->scheduled_at);
        $servicesText = $this->formatServicesText($appointment);

        $message = "Ciao {$clientName},\n" .
            "confermiamo l'appuntamento per {$dogName}\n" .
            $servicesText .
            "{$whenText}.\n" .
            "ZagaDogs";

        $phone = $this->normalizeItalianPhone((string) ($appointment->client?->phone ?? ''));

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    public function sendAppointmentReminder(Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'dog', 'services']);

        $clientName = trim((string) ($appointment->client?->first_name ?? ''));
        if ($clientName === '') {
            $clientName = trim((string) ($appointment->client?->last_name ?? ''));
        }

        $dogName = trim((string) ($appointment->dog?->name ?? ''));
        $whenText = $this->formatWhenText($appointment->scheduled_at);
        $servicesText = $this->formatServicesText($appointment);

        $message = "Ciao {$clientName},\n" .
            "ti ricordiamo l'appuntamento per {$dogName}\n" .
            $servicesText .
            "{$whenText}.\n" .
            "ZagaDogs";

        $phone = $this->normalizeItalianPhone((string) ($appointment->client?->phone ?? ''));

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

    private function normalizeItalianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (! str_starts_with($digits, '39')) {
            $digits = '39' . $digits;
        }

        return $digits;
    }

    private function formatServicesText(Appointment $appointment): string
    {
        $services = $appointment->services
            ->pluck('name')
            ->map(fn (?string $name) => trim((string) $name))
            ->filter()
            ->values();

        if ($services->isEmpty()) {
            return '';
        }

        return 'Servizi: ' . $services->join(', ') . "\n";
    }
}
