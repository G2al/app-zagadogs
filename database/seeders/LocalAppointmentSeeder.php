<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
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
        $faker = Faker::create('it_IT');

        $services = $this->services();
        $dogs = collect();

        for ($i = 0; $i < 50; $i++) {
            $client = Client::create([
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'phone' => $this->uniquePhone($faker),
                'notes' => $faker->optional(0.35)->sentence(),
            ]);

            $dogs->push(Dog::create([
                'client_id' => $client->id,
                'name' => $faker->firstName(),
                'breed' => $faker->randomElement([
                    'Labrador Retriever',
                    'Golden Retriever',
                    'Barboncino',
                    'Bulldog Francese',
                    'Pastore Tedesco',
                    'Beagle',
                    'Border Collie',
                    'Jack Russell Terrier',
                    'Cocker Spaniel',
                    'Meticcio',
                ]),
                'details' => $faker->optional(0.4)->sentence(),
            ]));
        }

        $statuses = ['pending', 'confirmed', 'completed'];
        $start = Carbon::create(2026, 5, 28);
        $end = Carbon::create(2026, 6, 2);

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $slots = $this->dailySlots($day, self::MIN_APPOINTMENTS_PER_DAY);

            foreach ($slots as $scheduledAt) {
                /** @var Dog $dog */
                $dog = $dogs->random();

                $appointment = Appointment::create([
                    'client_id' => $dog->client_id,
                    'dog_id' => $dog->id,
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

    private function services(): Collection
    {
        return collect([
            ['name' => 'Bagno', 'color' => '#2563eb'],
            ['name' => 'Toelettatura completa', 'color' => '#16a34a'],
            ['name' => 'Taglio unghie', 'color' => '#f59e0b'],
            ['name' => 'Stripping', 'color' => '#dc2626'],
            ['name' => 'Snodatura', 'color' => '#7c3aed'],
        ])->map(fn (array $service): Service => Service::firstOrCreate(
            ['name' => $service['name']],
            ['color' => $service['color']]
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
