<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ChangeClientStatusRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClientStatusController extends Controller
{
    private const REQUIRED_FOR_DEAL = [
        'passport_id',
        'reservation_form',
        'sales_contract',
        'payment_receipt',
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function store(ChangeClientStatusRequest $request, Client $client): ClientResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $to = $request->string('to')->toString();
        $from = $client->status;
        $reason = trim((string) $request->input('reason', ''));

        if ($from === $to) {
            return new ClientResource($client->load('agency'));
        }

        if ($to === 'reservation') {
            $count = $client->documents()->where('document_type', 'reservation_form')->count();
            if ($count < 1) {
                return ApiError::businessRule('Upload the Reservation Form before moving to Reservation.');
            }
        }

        if ($to === 'deal') {
            $have = $client->documents()->pluck('document_type')->all();
            $missing = array_values(array_diff(self::REQUIRED_FOR_DEAL, $have));
            if ($missing !== []) {
                return ApiError::businessRule('Cannot move client to deal because required documents are missing.', [
                    'missing_documents' => $missing,
                ]);
            }
        }

        DB::transaction(function () use ($client, $user, $to, $from, $reason): void {
            $client->status = $to;
            $client->updated_by = $user->id;
            $client->not_interested_reason = $to === 'not_interested' ? $reason : null;
            $client->save();

            $this->activityLogger->log(
                $client,
                'status_changed',
                $user,
                $reason !== '' ? 'Reason: '.$reason : null,
                $from,
                $to,
            );
        });

        return new ClientResource($client->fresh()->load('agency'));
    }
}
