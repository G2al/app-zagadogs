<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use App\Models\Service;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conferma e promemoria WhatsApp automatici. Nessuna chiamata di rete vera: WAHA è sempre simulato.
 *
 * Orari: gli appuntamenti sono salvati come "orario del negozio"; con l'ora di test 10:00 UTC
 * (autunno, Italia a UTC+1) l'orologio del negozio segna 2026-10-29 11:00.
 */
class WahaAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00', 'UTC'));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function configureWaha(): void
    {
        config([
            'services.waha.url' => 'http://waha.test',
            'services.waha.api_key' => 'chiave-segreta',
            'services.waha.session' => 'sessione-test',
        ]);
    }

    private function fakeWaha(int $status = 201): void
    {
        Http::fake(['waha.test/*' => Http::response(['id' => 'msg-1'], $status)]);
    }

    /** Esegue ciò che l'app rimanda a dopo la risposta HTTP (in produzione lo fa il server). */
    private function runDeferred(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }

    private function appointment(string $status = 'confirmed', string $when = '2026-11-02 15:00:00'): Appointment
    {
        $client = Client::firstOrCreate(
            ['phone' => '+39 333 111 2222'],
            ['first_name' => 'Anna', 'last_name' => 'Rossi'],
        );
        $dog = Dog::create(['client_id' => $client->id, 'name' => 'Briciola', 'breed' => 'Barboncino']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'dog_id' => $dog->id,
            'scheduled_at' => $when,
            'status' => $status,
        ]);
        $appointment->services()->sync([
            Service::firstOrCreate(['name' => 'Bagno'], ['color' => '#2563eb'])->id,
            Service::firstOrCreate(['name' => 'Taglio unghie'], ['color' => '#f59e0b'])->id,
        ]);

        return $appointment;
    }

    /** @return list<string> testi dei messaggi inviati a WAHA */
    private function sentTexts(): array
    {
        return Http::recorded()->map(fn (array $pair): string => $pair[0]->data()['text'])->values()->all();
    }

    // ------------------------------------------------------------ senza chiavi

    public function test_senza_chiavi_non_succede_niente_e_l_app_funziona(): void
    {
        Http::fake();

        $appointment = $this->appointment();
        $this->runDeferred();

        $this->assertSame('confirmed', $appointment->fresh()->status);
        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_messages', 0);

        $this->artisan('appointments:send-reminders')
            ->expectsOutputToContain('WAHA non configurato')
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------- conferma

    public function test_la_conferma_parte_da_sola_con_i_dati_giusti(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment();
        $this->runDeferred();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://waha.test/api/sendText'
                && $request->hasHeader('X-Api-Key', 'chiave-segreta')
                && $request['chatId'] === '393331112222@c.us'
                && $request['session'] === 'sessione-test'
                && str_contains($request['text'], 'Ciao Anna')
                && str_contains($request['text'], 'Confermiamo l\'appuntamento per Briciola')
                && str_contains($request['text'], 'lunedì 2 novembre 2026')
                && str_contains($request['text'], '15:00')
                && str_contains($request['text'], 'Bagno, Taglio unghie')
                && str_contains($request['text'], 'ZagaDogs');
        });

        $this->assertDatabaseHas('whatsapp_messages', [
            'appointment_id' => $appointment->id,
            'type' => 'confirmation',
            'status' => 'sent',
            'attempts' => 1,
        ]);
        $this->assertTrue($appointment->fresh()->whatsapp_sent);
    }

    public function test_salvare_di_nuovo_un_appuntamento_gia_confermato_non_reinvia(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment();
        $this->runDeferred();

        $appointment->update(['notes' => 'cambiata una nota']);
        $this->runDeferred();

        Http::assertSentCount(1);
    }

    public function test_da_in_attesa_a_confermato_invia_una_sola_volta(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('pending');
        $this->runDeferred();
        Http::assertNothingSent();

        $appointment->update(['status' => 'confirmed']);
        $appointment->update(['notes' => 'ancora una modifica']);
        $this->runDeferred();

        Http::assertSentCount(1);
    }

    public function test_se_waha_fallisce_l_appuntamento_resta_confermato(): void
    {
        $this->configureWaha();
        $this->fakeWaha(500);

        $appointment = $this->appointment();
        $this->runDeferred();

        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertFalse((bool) $appointment->fresh()->whatsapp_sent);
        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'confirmation', 'status' => 'failed']);
    }

    public function test_cliente_senza_cane_riceve_un_messaggio_generico(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $client = Client::create(['first_name' => 'Luca', 'last_name' => 'Verdi', 'phone' => '3481234567']);
        $dog = Dog::create(['client_id' => $client->id, 'name' => null]);
        Appointment::create(['client_id' => $client->id, 'dog_id' => $dog->id, 'scheduled_at' => '2026-11-02 09:30:00', 'status' => 'confirmed']);
        $this->runDeferred();

        $this->assertStringContainsString('Confermiamo il tuo appuntamento', $this->sentTexts()[0]);
    }

    // -------------------------------------------------------------- promemoria

    public function test_il_promemoria_viene_pianificato_24_ore_prima(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('confirmed', '2026-11-02 15:00:00');

        $this->assertDatabaseHas('whatsapp_messages', [
            'appointment_id' => $appointment->id,
            'type' => 'reminder',
            'status' => 'pending',
            'due_at' => '2026-11-01 15:00:00',
        ]);
    }

    public function test_fissato_a_meno_di_24_ore_il_promemoria_e_3_ore_prima(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        // Adesso (orologio del negozio) sono le 11:00 del 29: domani alle 10:30 sono 23,5 ore.
        $appointment = $this->appointment('confirmed', '2026-10-30 10:30:00');

        $this->assertDatabaseHas('whatsapp_messages', [
            'appointment_id' => $appointment->id,
            'type' => 'reminder',
            'status' => 'pending',
            'due_at' => '2026-10-30 08:00:00', // 07:30 è troppo presto: si aspetta le 08:00
        ]);
    }

    public function test_a_meno_di_3_ore_non_c_e_promemoria(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('confirmed', '2026-10-29 13:00:00');
        $this->runDeferred();

        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'reminder', 'status' => 'skipped']);
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        // Parte solo la conferma, nessun promemoria.
        $texts = $this->sentTexts();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('Confermiamo', $texts[0]);
    }

    public function test_il_comando_invia_il_promemoria_alla_scadenza_e_una_sola_volta(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('confirmed', '2026-11-02 15:00:00');
        $this->runDeferred();

        // Prima della scadenza: niente.
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertCount(1, $this->sentTexts());

        // Si arriva a domenica 1 novembre, 15:30 (orologio del negozio): il promemoria è scaduto.
        Carbon::setTestNow(Carbon::parse('2026-11-01 14:30:00', 'UTC'));
        $this->artisan('appointments:send-reminders')->expectsOutputToContain('1 inviati')->assertSuccessful();

        $texts = $this->sentTexts();
        $this->assertCount(2, $texts);
        $this->assertStringContainsString("Ti ricordiamo l'appuntamento di domani per Briciola", $texts[1]);
        $this->assertStringContainsString('15:00', $texts[1]);
        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'reminder', 'status' => 'sent']);

        // Secondo giro: nessun doppione.
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertCount(2, $this->sentTexts());
    }

    public function test_se_l_appuntamento_viene_annullato_il_promemoria_non_parte(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('confirmed', '2026-11-02 15:00:00');
        $this->runDeferred();
        $appointment->update(['status' => 'cancelled']);

        Carbon::setTestNow(Carbon::parse('2026-11-01 14:30:00', 'UTC'));
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertCount(1, $this->sentTexts()); // solo la conferma
        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'reminder', 'status' => 'skipped']);
    }

    public function test_se_l_appuntamento_viene_spostato_il_promemoria_segue_il_nuovo_orario(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $appointment = $this->appointment('confirmed', '2026-11-02 15:00:00');
        $this->runDeferred();
        $appointment->update(['scheduled_at' => '2026-11-04 10:00:00']);

        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'reminder', 'status' => 'pending', 'due_at' => '2026-11-03 10:00:00']);

        // Passa la scadenza del vecchio orario: non parte niente.
        Carbon::setTestNow(Carbon::parse('2026-11-01 14:30:00', 'UTC'));
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertCount(1, $this->sentTexts());

        // Passa la scadenza del nuovo orario: parte, con l'ora nuova.
        Carbon::setTestNow(Carbon::parse('2026-11-03 09:30:00', 'UTC'));
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $texts = $this->sentTexts();
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('10:00', $texts[1]);
    }

    public function test_se_waha_e_spento_il_promemoria_viene_ritentato_poi_si_rinuncia(): void
    {
        $this->configureWaha();
        $this->fakeWaha(500);

        $appointment = $this->appointment('confirmed', '2026-11-02 15:00:00');
        $this->runDeferred();

        Carbon::setTestNow(Carbon::parse('2026-11-01 14:30:00', 'UTC'));

        foreach (range(1, 5) as $ignored) {
            $this->artisan('appointments:send-reminders')->expectsOutputToContain('1 da ritentare')->assertSuccessful();
        }

        $this->artisan('appointments:send-reminders')->expectsOutputToContain('1 falliti')->assertSuccessful();
        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $appointment->id, 'type' => 'reminder', 'status' => 'failed', 'attempts' => 6]);

        $this->artisan('appointments:send-reminders')->expectsOutputToContain('0 falliti')->assertSuccessful();
    }

    public function test_aggiungendo_le_chiavi_dopo_i_promemoria_degli_appuntamenti_esistenti_vengono_pianificati(): void
    {
        Http::fake();

        // Appuntamenti creati quando WAHA non era ancora configurato.
        $first = $this->appointment('confirmed', '2026-11-02 15:00:00');
        $this->appointment('pending', '2026-11-03 15:00:00');
        $this->runDeferred();
        $this->assertDatabaseCount('whatsapp_messages', 0);

        // Si aggiungono le chiavi: al primo giro dello scheduler il promemoria compare, ma solo per il confermato.
        $this->configureWaha();
        $this->fakeWaha();
        $this->artisan('appointments:send-reminders')->expectsOutputToContain('1 pianificati')->assertSuccessful();

        $this->assertDatabaseHas('whatsapp_messages', ['appointment_id' => $first->id, 'type' => 'reminder', 'status' => 'pending', 'due_at' => '2026-11-01 15:00:00']);
        $this->assertDatabaseCount('whatsapp_messages', 1);

        // Il giro dopo non ripianifica.
        $this->artisan('appointments:send-reminders')->expectsOutputToContain('0 pianificati')->assertSuccessful();
    }

    public function test_il_comando_di_prova_usa_waha(): void
    {
        $this->configureWaha();
        $this->fakeWaha();

        $this->artisan('whatsapp:test', ['phone' => '3489039214', 'message' => 'Ciao test'])->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request['chatId'] === '393489039214@c.us' && $request['text'] === 'Ciao test');
    }
}
