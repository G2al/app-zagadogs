<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Service;

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
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    /**
     * Durata totale in minuti: somma dei servizi scelti (30 se nessuno).
     */
    public function durationMinutes(): int
    {
        $total = (int) $this->services->sum('duration_minutes');

        return $total > 0 ? $total : 30;
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
