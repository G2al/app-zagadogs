<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    /** Tavolozza per assegnare in automatico un colore diverso a ogni operatrice. */
    public const COLORS = [
        '#2563eb', '#16a34a', '#f59e0b', '#db2777', '#7c3aed',
        '#0ea5e9', '#ea580c', '#14b8a6', '#dc2626', '#84cc16',
    ];

    protected $table = 'staff';

    protected $fillable = [
        'name',
        'color',
    ];

    protected static function booted(): void
    {
        // Un'operatrice creata senza colore ne riceve subito uno non ancora usato.
        static::creating(function (self $staff): void {
            if (blank($staff->color)) {
                $staff->color = self::nextColor();
            }
        });
    }

    /** Primo colore della tavolozza non ancora assegnato; finiti i colori, si riparte dall'inizio. */
    public static function nextColor(): string
    {
        $used = self::query()->whereNotNull('color')->pluck('color')->map(fn (string $color) => strtolower($color))->all();

        foreach (self::COLORS as $color) {
            if (! in_array($color, $used, true)) {
                return $color;
            }
        }

        return self::COLORS[count($used) % count(self::COLORS)];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** Servizi che questa operatrice esegue (se un servizio non ha operatrici collegate lo possono fare tutte). */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }
}
