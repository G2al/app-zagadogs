<?php

namespace Tests\Unit;

use App\Services\ReminderPlanner;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tutti gli orari sono nello stesso "orario del negozio" (come vengono salvati gli appuntamenti).
 */
class ReminderPlannerTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string|null}> [prenotato, appuntamento, promemoria atteso] */
    public static function cases(): array
    {
        return [
            'prenotato con giorni di anticipo: 24 ore prima' => ['2026-10-12 10:00', '2026-10-15 15:00', '2026-10-14 15:00'],
            'prenotato 20 ore prima: 3 ore prima' => ['2026-10-14 18:50', '2026-10-15 15:00', '2026-10-15 12:00'],
            'meno di 3 ore: nessun promemoria' => ['2026-10-15 13:00', '2026-10-15 15:00', null],
            'esattamente 3 ore: nessun promemoria utile' => ['2026-10-15 12:30', '2026-10-15 15:00', null],
            'mattina presto: 3 ore prima sarebbe di notte, si va alle 08:00' => ['2026-10-14 18:50', '2026-10-15 09:00', '2026-10-15 08:00'],
            'ore 08:30 prenotato la sera prima: la sera prima non c\'è più tempo' => ['2026-10-14 20:30', '2026-10-15 08:30', null],
            'ore 08:30 prenotato da giorni: 24 ore prima' => ['2026-10-10 12:00', '2026-10-15 08:30', '2026-10-14 08:30'],
            'appuntamento tardi: 24 ore prima oltre le 21 si porta alle 21' => ['2026-10-10 12:00', '2026-10-15 22:00', '2026-10-14 21:00'],
        ];
    }

    #[DataProvider('cases')]
    public function test_due_time(string $bookedAt, string $appointment, ?string $expected): void
    {
        $due = ReminderPlanner::dueAt(Carbon::parse($appointment), Carbon::parse($bookedAt));

        if ($expected === null) {
            $this->assertNull($due);

            return;
        }

        $this->assertNotNull($due);
        $this->assertSame($expected . ':00', $due->toDateTimeString());
    }

    public function test_non_modifica_le_date_ricevute(): void
    {
        $scheduled = Carbon::parse('2026-10-15 15:00');
        $now = Carbon::parse('2026-10-12 10:00');

        ReminderPlanner::dueAt($scheduled, $now);

        $this->assertSame('2026-10-15 15:00:00', $scheduled->toDateTimeString());
        $this->assertSame('2026-10-12 10:00:00', $now->toDateTimeString());
    }
}
