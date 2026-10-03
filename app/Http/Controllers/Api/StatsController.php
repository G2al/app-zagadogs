<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StatsController extends Controller
{
    private const TIMEZONE = 'Europe/Rome';

    /** Stati che contano come lavoro effettivo (annullati e in attesa esclusi). */
    private const ACTIVE = ['confirmed', 'completed'];

    /**
     * Statistiche su un intervallo di giorni (estremi inclusi, formato Y-m-d, giorni in ora italiana).
     * Di default il mese corrente. Considera solo gli appuntamenti con data.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->query('from') ?? now(self::TIMEZONE)->startOfMonth()->toDateString(), self::TIMEZONE)->startOfDay();
        $to = Carbon::parse($request->query('to') ?? $from->copy()->endOfMonth()->toDateString(), self::TIMEZONE)->startOfDay();

        $days = $from->diffInDays($to) + 1;
        if ($days > 366) {
            throw ValidationException::withMessages(['to' => ['L\'intervallo massimo è di 366 giorni.']]);
        }

        $rangeEnd = $to->copy()->addDay();

        $appointments = $this->appointmentsBetween($from, $rangeEnd);
        $active = $appointments->filter(fn (Appointment $a) => in_array($a->status, self::ACTIVE, true));

        $previousFrom = $from->copy()->subDays($days);
        $previous = $this->appointmentsBetween($previousFrom, $from);
        $previousActive = $previous->filter(fn (Appointment $a) => in_array($a->status, self::ACTIVE, true));

        $byStatus = collect(['pending', 'confirmed', 'completed', 'cancelled'])
            ->mapWithKeys(fn (string $status) => [$status => $appointments->where('status', $status)->count()]);

        $minutes = $active->sum(fn (Appointment $a) => $a->durationMinutes());

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $from->copy()->subDay()->toDateString(),
            ],
            'totals' => [
                'appointments' => $appointments->count(),
                'active' => $active->count(),
                'by_status' => $byStatus,
                'cancellation_rate' => $this->percent($byStatus['cancelled'], $appointments->count()),
                'booked_minutes' => $minutes,
                'booked_hours' => round($minutes / 60, 1),
                'avg_duration_minutes' => $active->isEmpty() ? 0 : (int) round($minutes / $active->count()),
                'avg_per_day' => round($active->count() / $days, 1),
            ],
            'comparison' => [
                'previous_appointments' => $previous->count(),
                'previous_active' => $previousActive->count(),
                'appointments_change_percent' => $this->change($appointments->count(), $previous->count()),
                'active_change_percent' => $this->change($active->count(), $previousActive->count()),
            ],
            'per_day' => $this->perDay($from, $days, $appointments),
            'by_staff' => $this->byStaff($active),
            'by_service' => $this->byService($active),
            'by_weekday' => $this->byWeekday($active),
            'by_hour' => $this->byHour($active),
            'clients' => $this->clients($from, $rangeEnd, $active),
            'to_schedule' => Appointment::query()->pending()->whereNull('scheduled_at')->count(),
        ]);
    }

    private function appointmentsBetween(Carbon $from, Carbon $to): Collection
    {
        return Appointment::query()
            ->with(['client', 'staff', 'services'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $from->copy()->setTimezone(config('app.timezone')))
            ->where('scheduled_at', '<', $to->copy()->setTimezone(config('app.timezone')))
            ->get();
    }

    private function local(Appointment $appointment): Carbon
    {
        return $appointment->scheduled_at->copy()->setTimezone(self::TIMEZONE);
    }

    /** Un punto per ogni giorno dell'intervallo, anche a zero. */
    private function perDay(Carbon $from, int $days, Collection $appointments): array
    {
        $grouped = $appointments->groupBy(fn (Appointment $a) => $this->local($a)->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $grouped): array {
            $date = $from->copy()->addDays($offset)->toDateString();
            $day = $grouped->get($date, collect());

            return [
                'date' => $date,
                'appointments' => $day->whereIn('status', self::ACTIVE)->count(),
                'cancelled' => $day->where('status', 'cancelled')->count(),
            ];
        })->all();
    }

    /** Tutte le operatrici, anche quelle senza appuntamenti nel periodo. */
    private function byStaff(Collection $active): array
    {
        $grouped = $active->groupBy('staff_id');

        return Staff::orderBy('name')->get()->map(function (Staff $staff) use ($grouped): array {
            $items = $grouped->get($staff->id, collect());
            $minutes = $items->sum(fn (Appointment $a) => $a->durationMinutes());

            return [
                'staff_id' => $staff->id,
                'name' => $staff->name,
                'appointments' => $items->count(),
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
            ];
        })->sortByDesc('appointments')->values()->all();
    }

    /** Quante volte è stato richiesto ogni servizio (un appuntamento con più servizi conta per ciascuno). */
    private function byService(Collection $active): array
    {
        $counts = [];

        foreach ($active as $appointment) {
            foreach ($appointment->services as $service) {
                $counts[$service->id] = ($counts[$service->id] ?? 0) + 1;
            }
        }

        return Service::orderBy('name')->get()->map(fn (Service $service): array => [
            'service_id' => $service->id,
            'name' => $service->name,
            'color' => $service->color,
            'duration_minutes' => $service->duration_minutes,
            'count' => $counts[$service->id] ?? 0,
            'minutes' => ($counts[$service->id] ?? 0) * ($service->duration_minutes ?? 0),
        ])->sortByDesc('count')->values()->all();
    }

    /** Lunedì (1) … domenica (7). */
    private function byWeekday(Collection $active): array
    {
        $grouped = $active->groupBy(fn (Appointment $a) => $this->local($a)->dayOfWeekIso);

        return collect(range(1, 7))->map(fn (int $day): array => [
            'weekday' => $day,
            'appointments' => $grouped->get($day, collect())->count(),
        ])->all();
    }

    /** Ora di inizio (ora italiana), solo le ore in cui c'è almeno un appuntamento. */
    private function byHour(Collection $active): array
    {
        return $active
            ->groupBy(fn (Appointment $a) => $this->local($a)->hour)
            ->map(fn (Collection $items, int $hour): array => ['hour' => $hour, 'appointments' => $items->count()])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function clients(Carbon $from, Carbon $rangeEnd, Collection $active): array
    {
        $uniqueIds = $active->pluck('client_id')->unique();

        $returning = $uniqueIds->isEmpty() ? 0 : Appointment::query()
            ->whereIn('client_id', $uniqueIds)
            ->whereIn('status', self::ACTIVE)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $from->copy()->setTimezone(config('app.timezone')))
            ->distinct()
            ->count('client_id');

        $top = $active->groupBy('client_id')
            ->map(fn (Collection $items): array => [
                'client_id' => $items->first()->client_id,
                'name' => $this->clientName($items->first()->client),
                'phone' => $items->first()->client?->phone,
                'appointments' => $items->count(),
            ])
            ->sortByDesc('appointments')
            ->take(5)
            ->values()
            ->all();

        return [
            'total' => Client::count(),
            'new' => Client::query()
                ->where('created_at', '>=', $from->copy()->setTimezone(config('app.timezone')))
                ->where('created_at', '<', $rangeEnd->copy()->setTimezone(config('app.timezone')))
                ->count(),
            'served' => $uniqueIds->count(),
            'returning' => $returning,
            'top' => $top,
        ];
    }

    private function clientName(?Client $client): string
    {
        $name = trim(($client?->last_name ?? '') . ' ' . ($client?->first_name ?? ''));

        return $name !== '' ? $name : (string) ($client?->phone ?? 'Cliente');
    }

    private function percent(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total * 100, 1);
    }

    /** Variazione percentuale rispetto al periodo precedente; null se non c'è un termine di paragone. */
    private function change(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1);
    }
}
