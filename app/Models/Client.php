<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'notes',
    ];

    /**
     * Relazioni
     */

    public function dogs()
    {
        return $this->hasMany(Dog::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
