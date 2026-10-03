<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            // Facoltativa: null = non specificata (l'appuntamento userà la durata predefinita se nessun servizio ne ha una).
            'duration_minutes' => $this->duration_minutes,
            // Prezzo di listino in euro (null = nessun prezzo). Il prezzo reale si decide su ogni appuntamento.
            'price' => $this->price !== null ? (float) $this->price : null,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'parent_id' => $this->category->parent_id,
            ] : null),
        ];
    }
}
