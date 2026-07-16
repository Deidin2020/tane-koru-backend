<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ChangeClientStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in(['new', 'presentation_completed', 'follow_up', 'reservation', 'deal', 'not_interested'])],
            'reason' => ['nullable', 'string', Rule::requiredIf(fn () => $this->input('to') === 'not_interested')],
        ];
    }
}
