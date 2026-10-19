<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'duration_minutes',
        'category_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /** Operatrici che eseguono questo servizio. Nessuna collegata = lo possono fare tutte. */
    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class)
            ->using(AppointmentService::class)
            ->withPivot('price')
            ->withTimestamps();
    }
}
