<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un servizio visto dentro un appuntamento: `price` è il prezzo applicato in quell'appuntamento
 * (può essere diverso dal listino `default_price`).
 */
class AppointmentServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = $this->pivot?->price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'category_id' => $this->category_id,
            'duration_minutes' => $this->duration_minutes,
            'price' => $price !== null ? (float) $price : null,
            'default_price' => $this->price !== null ? (float) $this->price : null,
        ];
    }
}
