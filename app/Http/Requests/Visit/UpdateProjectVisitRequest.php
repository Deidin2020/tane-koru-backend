<?php

namespace App\Http\Requests\Visit;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectVisitRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agency_id' => ['sometimes', 'integer', Rule::exists('agencies_companies', 'id')->whereNull('deleted_at')],
            'visit_date' => ['sometimes', 'date'],
            'contact_person' => ['prohibited'],
            'phone' => ['prohibited'],
            'sales_rep_id' => ['sometimes', 'nullable', 'integer', Rule::exists('profiles', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_salesperson', true)->where('is_active', true))],
            'feedback' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
