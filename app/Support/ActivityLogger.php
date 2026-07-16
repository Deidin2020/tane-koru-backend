<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\User;

class ActivityLogger
{
    public function log(Client $client, string $type, User $user, ?string $message = null, ?string $fromStatus = null, ?string $toStatus = null): ClientActivity
    {
        $activity = ClientActivity::query()->create([
            'client_id' => $client->id,
            'activity_type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'user_id' => $user->id,
        ]);

        $client->forceFill([
            'last_activity_at' => now(),
        ])->save();

        return $activity;
    }
}
