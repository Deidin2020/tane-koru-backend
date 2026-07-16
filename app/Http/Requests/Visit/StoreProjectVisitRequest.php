<?php

namespace App\Http\Requests\Visit;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreProjectVisitRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies_companies', 'id')->whereNull('deleted_at')],
            'visit_date' => ['required', 'date'],
            'contact_person' => ['prohibited'],
            'phone' => ['prohibited'],
            'sales_rep_id' => ['nullable', 'integer', Rule::exists('profiles', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_salesperson', true)->where('is_active', true))],
            'feedback' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
