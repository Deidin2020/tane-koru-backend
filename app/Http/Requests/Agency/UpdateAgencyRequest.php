<?php

namespace App\Http\Requests\Agency;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in(['large_company', 'medium_company', 'small_agency', 'individual_agent'])],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
