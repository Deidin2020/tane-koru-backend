<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lead_source' => ['sometimes', Rule::in(['agency', 'direct', 'referral'])],
            'agency_id' => ['sometimes', 'nullable', 'integer', Rule::exists('agencies_companies', 'id')->whereNull('deleted_at')],
            'direct_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referral_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'required_unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'nullable', Rule::in(['cash', 'installments'])],
            'purchase_purpose' => ['sometimes', 'nullable', Rule::in(['citizenship', 'investment', 'residence'])],
            'visit_date' => ['sometimes', 'nullable', 'date'],
            'assigned_salesperson_id' => ['sometimes', 'nullable', 'integer', Rule::exists('profiles', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_salesperson', true)->where('is_active', true))],
            'presentation_completed' => ['sometimes', 'boolean'],
            'objection' => ['sometimes', 'nullable', 'string'],
            'offer_details' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['prohibited'],
            'not_interested_reason' => ['prohibited'],
            'project_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'last_activity_at' => ['prohibited'],
        ];
    }
}
