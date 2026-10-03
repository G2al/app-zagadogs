<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ClientResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Services\ReminderPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scheda economica di un cliente: quanto ha speso, quando e per cosa.
 *
 * Un appuntamento conta come "svolto" (e quindi come incasso) se è completato oppure confermato con data già passata.
 * I confermati futuri sono "in programma". Annullati e senza data non contano.
 */
class ClientSummaryController extends Controller
{
    private const TIMEZONE = ReminderPlanner::TIMEZONE;

    public function __invoke(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->query('from') ?? now(self::TIMEZONE)->startOfMonth()->toDateString(), self::TIMEZONE)->startOfDay();
        $to = Carbon::parse($request->query('to') ?? $from->copy()->endOfMonth()->toDateString(), self::TIMEZONE)->startOfDay();

        $fromUtc = $from->copy()->setTimezone(config('app.timezone'));
        $endUtc = $to->copy()->addDay()->setTimezone(config('app.timezone'));

        $all = Appointment::query()
            ->where('client_id', $client->id)
            ->whereNotNull('scheduled_at')
            ->with(['services', 'staff'])
            ->orderByDesc('scheduled_at')
            ->get();

        $period = $all->filter(fn (Appointment $a) => $a->scheduled_at >= $fromUtc && $a->scheduled_at < $endUtc)->values();

        $realizedAll = $all->filter(fn (Appointment $a) => $this->isRealized($a));

        return response()->json([
            'client' => new ClientResource($client),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'period_totals' => $this->totals($period),
            'lifetime' => $this->totals($all) + [
                'first_visit' => $realizedAll->last()?->scheduled_at?->toIso8601String(),
                'last_visit' => $realizedAll->first()?->scheduled_at?->toIso8601String(),
            ],
            'by_service' => $this->byService($period),
            'by_month' => $this->byMonth($realizedAll),
            'appointments' => AppointmentResource::collection($period),
        ]);
    }

    private function isRealized(Appointment $appointment): bool
    {
        return $appointment->status === 'completed'
            || ($appointment->status === 'confirmed' && $appointment->scheduled_at->isPast());
    }

    private function isUpcoming(Appointment $appointment): bool
    {
        return $appointment->status === 'confirmed' && $appointment->scheduled_at->isFuture();
    }

    private function totals(Collection $appointments): array
    {
        $realized = $appointments->filter(fn (Appointment $a) => $this->isRealized($a));
        $upcoming = $appointments->filter(fn (Appointment $a) => $this->isUpcoming($a));

        $spent = round($realized->sum(fn (Appointment $a) => $a->totalPrice()), 2);

        return [
            'appointments' => $realized->count() + $upcoming->count(),
            'realized' => $realized->count(),
            'spent' => $spent,
            'avg_ticket' => $realized->isEmpty() ? 0.0 : round($spent / $realized->count(), 2),
            'upcoming' => $upcoming->count(),
            'upcoming_value' => round($upcoming->sum(fn (Appointment $a) => $a->totalPrice()), 2),
            'cancelled' => $appointments->where('status', 'cancelled')->count(),
            // Appuntamenti svolti con almeno un servizio senza prezzo: il totale "speso" è per difetto.
            'unpriced_appointments' => $realized->filter(fn (Appointment $a) => $a->unpricedServicesCount() > 0)->count(),
        ];
    }

    /** Servizi ricevuti nel periodo (solo appuntamenti svolti), dal più richiesto. */
    private function byService(Collection $period): array
    {
        $rows = [];

        foreach ($period->filter(fn (Appointment $a) => $this->isRealized($a)) as $appointment) {
            foreach ($appointment->services as $service) {
                /** @var Service $service */
                $row = $rows[$service->id] ?? ['service_id' => $service->id, 'name' => $service->name, 'count' => 0, 'spent' => 0.0];
                $row['count']++;
                $row['spent'] = round($row['spent'] + (float) $service->pivot->price, 2);
                $rows[$service->id] = $row;
            }
        }

        return collect($rows)->sortByDesc('count')->values()->all();
    }

    /** Ultimi 12 mesi (compreso il corrente), anche quelli a zero, per il grafico. */
    private function byMonth(Collection $realized): array
    {
        $grouped = $realized->groupBy(fn (Appointment $a) => $a->scheduled_at->copy()->setTimezone(self::TIMEZONE)->format('Y-m'));
        $month = now(self::TIMEZONE)->startOfMonth()->subMonths(11);

        return collect(range(0, 11))->map(function (int $offset) use ($grouped, $month): array {
            $key = $month->copy()->addMonths($offset)->format('Y-m');
            $items = $grouped->get($key, collect());

            return [
                'month' => $key,
                'appointments' => $items->count(),
                'spent' => round($items->sum(fn (Appointment $a) => $a->totalPrice()), 2),
            ];
        })->all();
    }
}
