<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Carbon\Carbon;

class FollowUpRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'follow_up_date' => ['nullable', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && Carbon::parse($value)->isBefore(today())) {
                    $fail('The follow_up_date must not be in the past.');
                }
            }],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $note = trim((string) $this->input('note', ''));
            $date = $this->input('follow_up_date');

            if ($note === '' && ! $date) {
                $validator->errors()->add('note', 'A note or follow_up_date is required.');
            }
        });
    }
}
