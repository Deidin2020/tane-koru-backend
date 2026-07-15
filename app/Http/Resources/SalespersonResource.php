<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalespersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email ?? $this->user?->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
