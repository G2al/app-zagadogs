<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Staff;
use App\Models\Service;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LocalAppointmentSeeder extends Seeder
{
    private const MIN_APPOINTMENTS_PER_DAY = 20;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // I clienti sono finti: niente conferme WhatsApp automatiche durante il seed.
        Appointment::withoutEvents(fn () => $this->seed());
    }

    private function seed(): void
    {
        $faker = Faker::create('it_IT');

        $services = $this->services();
        $staff = $this->staff();
        $clients = collect();

        for ($i = 0; $i < 50; $i++) {
            $clients->push(Client::create([
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'phone' => $this->uniquePhone($faker),
                'notes' => $faker->optional(0.35)->sentence(),
            ]));
        }

        $statuses = ['pending', 'confirmed', 'completed'];
        $start = Carbon::create(2026, 5, 28);
        $end = Carbon::create(2026, 6, 2);

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $slots = $this->dailySlots($day, self::MIN_APPOINTMENTS_PER_DAY);

            foreach ($slots as $scheduledAt) {
                /** @var Client $client */
                $client = $clients->random();

                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'staff_id' => $staff->random()->id,
                    'scheduled_at' => $scheduledAt,
                    'notes' => $faker->optional(0.45)->sentence(),
                    'status' => $faker->randomElement($statuses),
                    'whatsapp_sent' => $faker->boolean(25),
                ]);

                $appointment->services()->sync(
                    $services->random($faker->numberBetween(1, min(2, $services->count())))->pluck('id')->all()
                );
            }
        }
    }

    private function uniquePhone(\Faker\Generator $faker): string
    {
        do {
            $phone = $faker->unique()->numerify('+39 3## ### ####');
        } while (Client::where('phone', $phone)->exists());

        return $phone;
    }

    private function staff(): Collection
    {
        return collect(['Giulia', 'Martina', 'Sara'])
            ->map(fn (string $name): Staff => Staff::firstOrCreate(['name' => $name]));
    }

    private function services(): Collection
    {
        return collect([
            ['name' => 'Manicure', 'color' => '#db2777', 'duration_minutes' => 45],
            ['name' => 'Pedicure', 'color' => '#7c3aed', 'duration_minutes' => 60],
            ['name' => 'Pulizia viso', 'color' => '#16a34a', 'duration_minutes' => 60],
            ['name' => 'Massaggio', 'color' => '#2563eb', 'duration_minutes' => 50],
            ['name' => 'Ceretta', 'color' => '#f59e0b', 'duration_minutes' => 30],
        ])->map(fn (array $service): Service => Service::firstOrCreate(
            ['name' => $service['name']],
            ['color' => $service['color'], 'duration_minutes' => $service['duration_minutes']]
        ));
    }

    private function dailySlots(Carbon $day, int $count): Collection
    {
        $availableSlots = collect();
        $time = $day->copy()->setTime(8, 30);

        while ($time->lte($day->copy()->setTime(18, 30))) {
            $availableSlots->push($time->copy());
            $time->addMinutes(30);

            if ($time->hour === 13) {
                $time->setTime(14, 30);
            }
        }

        return collect(range(1, $count))
            ->map(fn (): Carbon => $availableSlots->random()->copy())
            ->sort()
            ->values();
    }
}
