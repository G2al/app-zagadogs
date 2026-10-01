<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Decide quando mandare il promemoria di un appuntamento (funzione pura, senza database).
 *
 * - normalmente 24 ore prima;
 * - se l'appuntamento è stato fissato a meno di 24 ore di distanza: 3 ore prima;
 * - se mancano meno di 3 ore al momento della conferma: nessun promemoria (basta la conferma);
 * - mai fuori dalla fascia 08:00-21:00 (ora italiana).
 */
final class ReminderPlanner
{
    public const TIMEZONE = 'Europe/Rome';

    public const LEAD_HOURS = 24;
    public const SHORT_LEAD_HOURS = 3;
    public const MIN_LEAD_HOURS = 3;
    public const WINDOW_START = 8;
    public const WINDOW_END = 21;

    /** Margine minimo tra promemoria e inizio dell'appuntamento. */
    private const MIN_NOTICE_MINUTES = 30;

    /**
     * @param  Carbon  $scheduledAt  inizio dell'appuntamento
     * @param  Carbon  $now          momento della conferma
     * @return Carbon|null  orario di invio in UTC, oppure null se non serve un promemoria
     */
    public static function dueAt(Carbon $scheduledAt, Carbon $now): ?Carbon
    {
        $scheduled = $scheduledAt->copy()->setTimezone(self::TIMEZONE);
        $now = $now->copy()->setTimezone(self::TIMEZONE);

        if ($scheduled->lt($now->copy()->addHours(self::MIN_LEAD_HOURS))) {
            return null;
        }

        $due = $scheduled->copy()->subHours(self::LEAD_HOURS);

        if ($due->lte($now)) {
            $due = $scheduled->copy()->subHours(self::SHORT_LEAD_HOURS);
        }

        $due = self::insideWindow($due, $scheduled);

        if ($due->lt($now) || $due->gt($scheduled->copy()->subMinutes(self::MIN_NOTICE_MINUTES))) {
            return null;
        }

        return $due->setTimezone('UTC');
    }

    private static function insideWindow(Carbon $due, Carbon $scheduled): Carbon
    {
        $dayStart = $due->copy()->setTime(self::WINDOW_START, 0);
        $dayEnd = $due->copy()->setTime(self::WINDOW_END, 0);

        if ($due->gt($dayEnd)) {
            return $dayEnd;
        }

        if ($due->lt($dayStart)) {
            // Prima il mattino stesso (se resta almeno un'ora di preavviso), altrimenti la sera prima.
            return $dayStart->lte($scheduled->copy()->subHour())
                ? $dayStart
                : $due->copy()->subDay()->setTime(self::WINDOW_END - 1, 0);
        }

        return $due;
    }
}
