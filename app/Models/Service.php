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
    ];

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class)->withTimestamps();
    }
}
