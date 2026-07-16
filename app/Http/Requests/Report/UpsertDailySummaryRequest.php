<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\ApiFormRequest;

class UpsertDailySummaryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
            'summary' => ['nullable', 'string'],
        ];
    }
}
