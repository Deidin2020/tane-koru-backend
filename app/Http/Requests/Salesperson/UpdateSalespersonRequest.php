<?php

namespace App\Http\Requests\Salesperson;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateSalespersonRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('profiles', 'email')
                    ->ignore($this->route('salesperson'))
                    ->whereNull('deleted_at'),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
