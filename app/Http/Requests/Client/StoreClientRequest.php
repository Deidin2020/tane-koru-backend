<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('currency') || $this->input('currency') === null || $this->input('currency') === '') {
            $this->merge(['currency' => 'USD']);
        }
    }

    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'lead_source' => ['required', Rule::in(['agency', 'direct', 'referral'])],
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies_companies', 'id')->whereNull('deleted_at'), Rule::requiredIf(fn () => $this->input('lead_source') === 'agency')],
            'direct_source' => ['nullable', 'string', 'max:255'],
            'referral_name' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'required_unit' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in(['cash', 'installments'])],
            'purchase_purpose' => ['nullable', Rule::in(['citizenship', 'investment', 'residence'])],
            'visit_date' => ['nullable', 'date'],
            'assigned_salesperson_id' => ['nullable', 'integer', Rule::exists('profiles', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_salesperson', true)->where('is_active', true))],
            'presentation_completed' => ['sometimes', 'boolean'],
            'objection' => ['nullable', 'string'],
            'offer_details' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
