<?php

namespace App\Http\Requests\Salesperson;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreSalespersonRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('profiles', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
