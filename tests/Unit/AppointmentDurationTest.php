<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use PHPUnit\Framework\TestCase;

class AppointmentDurationTest extends TestCase
{
    public function test_senza_durata_vale_zero(): void
    {
        $this->assertSame(0, (new Appointment())->durationMinutes());
        $this->assertSame(0, (new Appointment(['duration_minutes' => null]))->durationMinutes());
    }

    public function test_la_durata_e_quella_dell_appuntamento(): void
    {
        $this->assertSame(45, (new Appointment(['duration_minutes' => 45]))->durationMinutes());
        $this->assertSame(30, (new Appointment(['duration_minutes' => 30]))->durationMinutes());
    }

    public function test_la_durata_dei_servizi_non_conta(): void
    {
        $appointment = new Appointment(['duration_minutes' => null]);
        $appointment->setRelation('services', collect([
            new Service(['duration_minutes' => 60]),
            new Service(['duration_minutes' => 45]),
        ]));

        $this->assertSame(0, $appointment->durationMinutes());

        $appointment->duration_minutes = 20;
        $this->assertSame(20, $appointment->durationMinutes());
    }
}
