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
        'duration_minutes',
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

    /** Solo per disegnare i blocchi nel calendario Filament: un appuntamento lungo 0 minuti non si vedrebbe. */
    public const MIN_CALENDAR_BLOCK_MINUTES = 30;

    /**
     * Durata dell'appuntamento in minuti: è un dato dell'appuntamento, non dei servizi.
     * Facoltativa: se non è specificata vale 0 e la fine (ends_at) coincide con l'inizio.
     */
    public function durationMinutes(): int
    {
        return max(0, (int) $this->duration_minutes);
    }

    public const DEFAULT_COLOR = '#16a34a';

    /**
     * Colore con cui l'appuntamento appare nel calendario: quello dell'operatrice.
     * Se l'operatrice non ne ha uno vale quello del primo servizio, poi un verde predefinito.
     * Usa solo le relazioni già caricate, per non fare una query per ogni appuntamento.
     */
    public function displayColor(): string
    {
        $staffColor = $this->relationLoaded('staff') ? $this->staff?->color : null;

        if (filled($staffColor)) {
            return $staffColor;
        }

        $serviceColor = $this->relationLoaded('services') ? $this->services->pluck('color')->filter()->first() : null;

        return $serviceColor ?: self::DEFAULT_COLOR;
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
