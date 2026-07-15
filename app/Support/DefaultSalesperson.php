<?php

namespace App\Support;

use App\Models\Profile;
use Illuminate\Http\Exceptions\HttpResponseException;

class DefaultSalesperson
{
    public function idOrFail(): int
    {
        $id = Profile::query()
            ->where('is_salesperson', true)
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');

        if (! $id) {
            throw new HttpResponseException(
                ApiError::make(
                    422,
                    'DEFAULT_SALESPERSON_REQUIRED',
                    'An active default salesperson must be configured before creating this record.'
                )->toResponse(request())
            );
        }

        return (int) $id;
    }
}
