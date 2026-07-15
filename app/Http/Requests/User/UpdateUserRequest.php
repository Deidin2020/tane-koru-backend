<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
