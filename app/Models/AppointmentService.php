<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Riga della tabella ponte appuntamento-servizio, con il prezzo applicato in quell'appuntamento.
 * Quando un servizio viene collegato senza prezzo si copia il prezzo di listino di quel momento:
 * cambiare il listino dopo non modifica gli appuntamenti già fatti.
 */
class AppointmentService extends Pivot
{
    protected $table = 'appointment_service';

    public $incrementing = true;

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pivot): void {
            if ($pivot->price === null) {
                $pivot->price = Service::query()->whereKey($pivot->service_id)->value('price');
            }
        });
    }
}
