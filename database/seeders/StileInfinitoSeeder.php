<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dati di prova per Flora Stile Infinito: 4 staff, 10 servizi con durate diverse,
 * 50 clienti e 100 appuntamenti (senza sovrapposizioni sulla stessa operatrice).
 * Non usa Faker, quindi funziona anche in produzione (composer --no-dev).
 *
 * php artisan db:seed --class=StileInfinitoSeeder
 */
class StileInfinitoSeeder extends Seeder
{
    private const CLIENTS = 50;
    private const APPOINTMENTS = 100;
    private const UNSCHEDULED = 12;      // "da programmare": pending, senza data
    private const DAYS_BACK = 10;
    private const DAYS_FORWARD = 14;
    private const TIMEZONE = 'Europe/Rome';
    private const OPEN = 9;              // 09:00
    private const CLOSE = 19;            // 19:00

    private const FIRST_NAMES = [
        'Giulia', 'Martina', 'Sara', 'Chiara', 'Francesca', 'Elisa', 'Alessia', 'Valentina', 'Federica', 'Silvia',
        'Laura', 'Elena', 'Anna', 'Paola', 'Roberta', 'Ilaria', 'Simona', 'Claudia', 'Sofia', 'Marta',
        'Arianna', 'Beatrice', 'Camilla', 'Denise', 'Eleonora', 'Gaia', 'Irene', 'Lucia', 'Monica', 'Noemi',
    ];

    private const LAST_NAMES = [
        'Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo', 'Ricci', 'Marino', 'Greco',
        'Bruno', 'Gallo', 'Conti', 'De Luca', 'Mancini', 'Costa', 'Giordano', 'Rizzo', 'Lombardi', 'Moretti',
        'Barbieri', 'Fontana', 'Santoro', 'Mariani', 'Rinaldi', 'Caruso', 'Ferrara', 'Galli', 'Martini', 'Leone',
    ];

    private const NOTES = [
        'Preferisce il mattino.',
        'Pelle sensibile, usare prodotti delicati.',
        'Cliente abituale.',
        'Porta la figlia.',
        'Richiede smalto rosso.',
        'Arriva in ritardo di 10 minuti.',
        'Allergica al nichel.',
        'Prima visita.',
    ];

    public function run(): void
    {
        $staff = $this->staff();
        $services = $this->services();
        $clients = $this->clients();

        /** @var array<int, array<int, array{0: Carbon, 1: Carbon}>> $busy occupazione per operatrice */
        $busy = [];

        $created = 0;

        for ($i = 0; $i < self::APPOINTMENTS; $i++) {
            $chosen = $services->random(random_int(1, 3));
            $duration = max(30, (int) $chosen->sum('duration_minutes'));
            $operator = $staff->random();

            $unscheduled = $i < self::UNSCHEDULED;
            $start = $unscheduled ? null : $this->freeSlot($operator->id, $duration, $busy);

            if (! $unscheduled && ! $start) {
                continue;
            }

            $appointment = Appointment::create([
                'client_id' => $clients->random()->id,
                'staff_id' => $operator->id,
                'scheduled_at' => $start?->copy()->setTimezone('UTC'),
                'notes' => random_int(1, 100) <= 40 ? self::NOTES[array_rand(self::NOTES)] : null,
                'status' => $this->status($start),
                'whatsapp_sent' => $start !== null && random_int(1, 100) <= 60,
            ]);

            $appointment->services()->sync($chosen->pluck('id')->all());
            $created++;
        }

        $this->command?->info("StileInfinitoSeeder: {$staff->count()} staff, {$services->count()} servizi, {$clients->count()} clienti, {$created} appuntamenti.");
    }

    private function staff(): Collection
    {
        return collect(['Giulia', 'Martina', 'Sara', 'Elena'])
            ->map(fn (string $name): Staff => Staff::firstOrCreate(['name' => $name]));
    }

    private function services(): Collection
    {
        return collect([
            ['name' => 'Epilazione laser', 'duration_minutes' => 20, 'color' => '#0ea5e9'],
            ['name' => 'Ceretta gambe', 'duration_minutes' => 30, 'color' => '#f59e0b'],
            ['name' => 'Trucco', 'duration_minutes' => 40, 'color' => '#ec4899'],
            ['name' => 'Manicure', 'duration_minutes' => 45, 'color' => '#db2777'],
            ['name' => 'Massaggio rilassante', 'duration_minutes' => 50, 'color' => '#2563eb'],
            ['name' => 'Pedicure', 'duration_minutes' => 60, 'color' => '#7c3aed'],
            ['name' => 'Semipermanente', 'duration_minutes' => 75, 'color' => '#14b8a6'],
            ['name' => 'Ceretta corpo', 'duration_minutes' => 90, 'color' => '#ea580c'],
            ['name' => 'Trattamento corpo', 'duration_minutes' => 105, 'color' => '#16a34a'],
            ['name' => 'Pacchetto sposa', 'duration_minutes' => 120, 'color' => '#dc2626'],
        ])->map(fn (array $service): Service => Service::updateOrCreate(
            ['name' => $service['name']],
            ['duration_minutes' => $service['duration_minutes'], 'color' => $service['color']]
        ));
    }

    private function clients(): Collection
    {
        return collect(range(1, self::CLIENTS))->map(fn (): Client => Client::create([
            'first_name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
            'last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
            'phone' => $this->uniquePhone(),
            'notes' => random_int(1, 100) <= 25 ? self::NOTES[array_rand(self::NOTES)] : null,
        ]));
    }

    private function uniquePhone(): string
    {
        do {
            $phone = sprintf('+39 3%02d %03d %04d', random_int(20, 99), random_int(0, 999), random_int(0, 9999));
        } while (Client::where('phone', $phone)->exists());

        return $phone;
    }

    /**
     * Cerca un orario libero (ora locale, passo da 15 minuti) senza sovrapporsi
     * agli altri appuntamenti della stessa operatrice. Domenica esclusa.
     */
    private function freeSlot(int $staffId, int $duration, array &$busy): ?Carbon
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $day = Carbon::now(self::TIMEZONE)
                ->startOfDay()
                ->addDays(random_int(-self::DAYS_BACK, self::DAYS_FORWARD));

            if ($day->isSunday()) {
                continue;
            }

            $minutesOpen = (self::CLOSE - self::OPEN) * 60;
            $latestStart = $minutesOpen - $duration;

            if ($latestStart < 0) {
                return null;
            }

            $start = $day->copy()->setTime(self::OPEN, 0)->addMinutes(random_int(0, intdiv($latestStart, 15)) * 15);
            $end = $start->copy()->addMinutes($duration);

            foreach ($busy[$staffId] ?? [] as [$busyStart, $busyEnd]) {
                if ($start->lt($busyEnd) && $end->gt($busyStart)) {
                    continue 2;
                }
            }

            $busy[$staffId][] = [$start, $end];

            return $start;
        }

        return null;
    }

    private function status(?Carbon $start): string
    {
        if ($start === null) {
            return 'pending';
        }

        $roll = random_int(1, 100);

        if ($start->isPast()) {
            return $roll <= 85 ? 'completed' : 'cancelled';
        }

        return $roll <= 92 ? 'confirmed' : 'cancelled';
    }
}
