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
            // Colore del servizio: non è più usato nel calendario (conta quello dell'operatrice).
            'color' => $this->color,
            // Non più usata: la durata è un dato dell'appuntamento.
            'duration_minutes' => $this->duration_minutes,
            // Prezzo di listino in euro (null = nessun prezzo). Il prezzo reale si decide su ogni appuntamento.
            'price' => $this->price !== null ? (float) $this->price : null,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'parent_id' => $this->category->parent_id,
            ] : null),
            // Operatrici che eseguono il servizio. Lista vuota = nessun collegamento: lo possono fare tutte.
            'staff_ids' => $this->whenLoaded('staff', fn () => $this->staff->pluck('id')->values()),
            'staff' => $this->whenLoaded('staff', fn () => $this->staff
                ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'color' => $member->color])
                ->values()),
        ];
    }
}
