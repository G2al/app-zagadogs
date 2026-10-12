<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use PHPUnit\Framework\TestCase;

class AppointmentDurationTest extends TestCase
{
    /** @param  array<int, int|null>  $durations */
    private function appointmentWith(array $durations): Appointment
    {
        $appointment = new Appointment();
        $appointment->setRelation('services', collect(array_map(
            fn (?int $minutes) => new Service(['duration_minutes' => $minutes]),
            $durations,
        )));

        return $appointment;
    }

    public function test_senza_servizi_dura_zero(): void
    {
        $this->assertSame(0, $this->appointmentWith([])->durationMinutes());
    }

    public function test_servizi_senza_durata_danno_zero(): void
    {
        $this->assertSame(0, $this->appointmentWith([null, null])->durationMinutes());
    }

    public function test_somma_solo_le_durate_presenti(): void
    {
        $this->assertSame(45, $this->appointmentWith([null, 45])->durationMinutes());
        $this->assertSame(105, $this->appointmentWith([45, 60, null])->durationMinutes());
    }

    public function test_un_servizio_da_30_minuti_resta_30(): void
    {
        $this->assertSame(30, $this->appointmentWith([30])->durationMinutes());
    }
}
