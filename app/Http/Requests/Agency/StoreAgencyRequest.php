<?php

namespace App\Http\Requests\Agency;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(['large_company', 'medium_company', 'small_agency', 'individual_agent'])],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
