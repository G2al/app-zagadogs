<?php

namespace App\Models;

use App\Observers\AppointmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Service;

#[ObservedBy(AppointmentObserver::class)]
class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'staff_id',
        'scheduled_at',
        'notes',
        'status',
        'whatsapp_sent',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'whatsapp_sent' => 'boolean',
    ];

    /**
     * Relazioni
     */

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->using(AppointmentService::class)
            ->withPivot('price')
            ->withTimestamps();
    }

    public const DEFAULT_DURATION_MINUTES = 30;

    /**
     * Durata totale in minuti: somma dei servizi scelti che hanno una durata.
     * Se nessun servizio ne ha una (la durata è facoltativa) vale il predefinito.
     */
    public function durationMinutes(): int
    {
        $total = (int) $this->services->sum('duration_minutes');

        return $total > 0 ? $total : self::DEFAULT_DURATION_MINUTES;
    }

    /** Totale in euro: somma dei prezzi applicati ai servizi (quelli senza prezzo contano zero). */
    public function totalPrice(): float
    {
        return round((float) $this->services->sum(fn (Service $service) => (float) $service->pivot->price), 2);
    }

    /** Quanti servizi non hanno un prezzo, per segnalare un totale incompleto. */
    public function unpricedServicesCount(): int
    {
        return $this->services->filter(fn (Service $service) => $service->pivot->price === null)->count();
    }

    /**
     * Scopes utili (ci serviranno in Dashboard e Calendar)
     */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }
}
