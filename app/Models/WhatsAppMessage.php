<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    public const CONFIRMATION = 'confirmation';
    public const REMINDER = 'reminder';

    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'appointment_id',
        'type',
        'for_scheduled_at',
        'status',
        'due_at',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected $casts = [
        'for_scheduled_at' => 'datetime',
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
