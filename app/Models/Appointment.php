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
        'dog_id',
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

    public function dog()
    {
        return $this->belongsTo(Dog::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    /** Messaggi WhatsApp automatici (conferma e promemoria) di questo appuntamento. */
    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
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
