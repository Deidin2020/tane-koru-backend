<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->profile?->full_name ?? $this->name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
