<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'agency_id' => $this->agency_id,
            'agency_name' => $this->whenLoaded('agency', fn () => $this->agency?->name),
            'visit_date' => optional($this->visit_date)?->toISOString(),
            'contact_person' => $this->whenLoaded('agency', fn () => $this->agency?->contact_person),
            'phone' => $this->whenLoaded('agency', fn () => $this->agency?->phone),
            'sales_rep_id' => $this->sales_rep_id,
            'feedback' => $this->feedback,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
            'agency' => new AgencyLookupResource($this->whenLoaded('agency')),
        ];
    }
}
