<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'ends_at' => $this->scheduled_at?->copy()->addMinutes($this->durationMinutes())->toIso8601String(),
            'duration_minutes' => $this->durationMinutes(),
            'status' => $this->status,
            'notes' => $this->notes,
            'whatsapp_sent' => $this->whatsapp_sent,
            'client_id' => $this->client_id,
            'staff_id' => $this->staff_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'staff' => new StaffResource($this->whenLoaded('staff')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
