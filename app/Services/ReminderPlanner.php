<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Decide quando mandare il promemoria di un appuntamento (funzione pura, senza database).
 *
 * - normalmente 24 ore prima;
 * - se l'appuntamento è stato fissato a meno di 24 ore di distanza: 3 ore prima;
 * - se mancano meno di 3 ore al momento della conferma: nessun promemoria (basta la conferma);
 * - mai fuori dalla fascia 08:00-21:00.
 *
 * ORARI: in questa app gli orari degli appuntamenti sono salvati come li scrive la segretaria
 * (l'ora "dell'orologio" del negozio), non convertiti da un fuso. Per questo tutte le date di questa classe
 * sono nello stesso "orario del negozio" e per sapere "che ora è adesso" si usa wallNow(), mai now().
 */
final class ReminderPlanner
{
    /** Fuso del negozio: serve solo a leggere l'ora dell'orologio di adesso. */
    public const BUSINESS_TIMEZONE = 'Europe/Rome';

    public const LEAD_HOURS = 24;
    public const SHORT_LEAD_HOURS = 3;
    public const MIN_LEAD_HOURS = 3;
    public const WINDOW_START = 8;
    public const WINDOW_END = 21;

    /** Margine minimo tra promemoria e inizio dell'appuntamento. */
    private const MIN_NOTICE_MINUTES = 30;

    /** "Adesso" nello stesso orario del negozio con cui sono salvati gli appuntamenti. */
    public static function wallNow(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            now(self::BUSINESS_TIMEZONE)->format('Y-m-d H:i:s'),
            config('app.timezone'),
        );
    }

    /**
     * @param  Carbon  $scheduledAt  inizio dell'appuntamento (orario del negozio)
     * @param  Carbon  $now          momento della conferma, nello stesso orario (vedi wallNow())
     * @return Carbon|null  orario di invio nello stesso orario, oppure null se non serve un promemoria
     */
    public static function dueAt(Carbon $scheduledAt, Carbon $now): ?Carbon
    {
        $scheduled = $scheduledAt->copy();
        $now = $now->copy();

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

        return $due;
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
