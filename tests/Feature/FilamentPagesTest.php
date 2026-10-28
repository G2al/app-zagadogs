<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prova che le pagine del pannello Filament si disegnano senza errori, sia senza WAHA
 * (con il pulsante e la spunta per aprire WhatsApp a mano) sia con WAHA configurato.
 */
class FilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/admin',
        '/admin/clients',
        '/admin/clients/create',
        '/admin/dogs',
        '/admin/dogs/create',
        '/admin/services',
        '/admin/services/create',
        '/admin/appointments',
        '/admin/appointments/create',
    ];

    private function login(): Appointment
    {
        $client = Client::create(['first_name' => 'Anna', 'last_name' => 'Rossi', 'phone' => '+39 333 111 2222']);
        $dog = Dog::create(['client_id' => $client->id, 'name' => 'Briciola', 'breed' => 'Barboncino']);
        $appointment = Appointment::create(['client_id' => $client->id, 'dog_id' => $dog->id, 'scheduled_at' => now()->addDays(2), 'status' => 'confirmed']);
        $appointment->services()->sync([Service::create(['name' => 'Bagno'])->id]);
        Appointment::create(['client_id' => $client->id, 'dog_id' => $dog->id, 'status' => 'pending']);

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'email_verified_at' => now(),
        ]));

        return $appointment;
    }

    public function test_le_pagine_si_aprono_senza_waha(): void
    {
        $appointment = $this->login();

        foreach ([...self::PAGES, "/admin/appointments/{$appointment->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_le_pagine_si_aprono_con_waha_configurato(): void
    {
        config(['services.waha.url' => 'http://waha.test', 'services.waha.api_key' => 'chiave', 'services.waha.session' => 'sessione']);
        $appointment = $this->login();

        foreach ([...self::PAGES, "/admin/appointments/{$appointment->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
