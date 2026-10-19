<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prova che le pagine del pannello Filament si disegnano senza errori
 * (form, tabelle e calendario costruiti con i campi attuali).
 */
class FilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_pagine_del_pannello_si_aprono(): void
    {
        $staff = Staff::create(['name' => 'Giulia']);
        $service = Service::create(['name' => 'Manicure', 'price' => 25]);
        $service->staff()->sync([$staff->id]);
        $client = Client::create(['first_name' => 'Anna', 'last_name' => 'Rossi', 'phone' => '+39 333 111 2222']);

        $appointment = Appointment::withoutEvents(fn () => Appointment::create([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
            'status' => 'confirmed',
        ]));
        $appointment->services()->sync([$service->id]);

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'email_verified_at' => now(),
        ]));

        foreach ([
            '/admin',
            '/admin/staff',
            '/admin/staff/create',
            "/admin/staff/{$staff->id}/edit",
            '/admin/services',
            '/admin/services/create',
            "/admin/services/{$service->id}/edit",
            '/admin/service-categories',
            '/admin/service-categories/create',
            '/admin/appointments',
            '/admin/appointments/create',
            "/admin/appointments/{$appointment->id}/edit",
            '/admin/clients',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_un_operatore_nuovo_riceve_un_colore(): void
    {
        $first = Staff::create(['name' => 'Prima']);
        $second = Staff::create(['name' => 'Seconda']);

        $this->assertNotEmpty($first->color);
        $this->assertNotEmpty($second->color);
        $this->assertNotSame($first->color, $second->color);
    }

    public function test_il_colore_dell_appuntamento_e_quello_dell_operatrice(): void
    {
        $staff = Staff::create(['name' => 'Giulia', 'color' => '#123456']);
        $appointment = new Appointment();
        $appointment->setRelation('staff', $staff);

        $this->assertSame('#123456', $appointment->displayColor());

        $appointment->setRelation('staff', null);
        $this->assertSame(Appointment::DEFAULT_COLOR, $appointment->displayColor());
    }
}
