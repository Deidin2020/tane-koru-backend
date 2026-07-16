<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;

class StoreClientActivityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
        ];
    }
}
