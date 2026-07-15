<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class ApiTokenService
{
    public function issue(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'jti' => (string) Str::uuid(),
            'iat' => now()->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function resolve(string $token): ?array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['sub'], $decoded['jti'])) {
            return null;
        }

        if (Cache::get($this->revocationKey($decoded['jti']))) {
            return null;
        }

        $user = User::query()
            ->with(['profile', 'roles'])
            ->find($decoded['sub']);

        if (! $user || ! $user->is_active) {
            return null;
        }

        return [
            'user' => $user,
            'payload' => $decoded,
        ];
    }

    public function revoke(array $payload): void
    {
        Cache::put(
            $this->revocationKey((string) $payload['jti']),
            true,
            Carbon::now()->addDays(30),
        );
    }

    private function revocationKey(string $jti): string
    {
        return "api_token_revoked:{$jti}";
    }
}
