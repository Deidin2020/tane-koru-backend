<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'agency_id' => $this->agency_id,
            'agency_name' => $this->whenLoaded('agency', fn () => $this->agency?->name),
            'client_name' => $this->client_name,
            'phone' => $this->phone,
            'nationality' => $this->nationality,
            'lead_source' => $this->lead_source,
            'direct_source' => $this->direct_source,
            'referral_name' => $this->referral_name,
            'budget' => $this->budget,
            'currency' => $this->currency,
            'required_unit' => $this->required_unit,
            'payment_method' => $this->payment_method,
            'purchase_purpose' => $this->purchase_purpose,
            'visit_date' => optional($this->visit_date)?->format('Y-m-d'),
            'assigned_salesperson_id' => $this->assigned_salesperson_id,
            'presentation_completed' => (bool) $this->presentation_completed,
            'objection' => $this->objection,
            'offer_details' => $this->offer_details,
            'notes' => $this->notes,
            'status' => $this->status,
            'not_interested_reason' => $this->not_interested_reason,
            'follow_up_date' => optional($this->follow_up_date)?->format('Y-m-d'),
            'last_activity_at' => optional($this->last_activity_at)?->toISOString(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
            'agency' => new AgencyLookupResource($this->whenLoaded('agency')),
        ];
    }
}
