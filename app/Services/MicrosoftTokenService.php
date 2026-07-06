<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftTokenService
{
    public function accessToken(User $user): ?string
    {
        $token = (string) ($user->azure_access_token ?? '');
        if ($token === '') {
            return null;
        }

        if ($user->azure_token_expires_at !== null && $user->azure_token_expires_at->isFuture()) {
            return $token;
        }

        return $this->refreshAccessToken($user);
    }

    protected function refreshAccessToken(User $user): ?string
    {
        $refreshToken = (string) ($user->azure_refresh_token ?? '');
        if ($refreshToken === '') {
            return null;
        }

        $tenant = (string) config('services.azure.tenant', 'common');
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => config('services.azure.client_id'),
            'client_secret' => config('services.azure.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => 'openid profile email offline_access User.Read Mail.Send',
        ]);

        if (! $response->successful()) {
            Log::warning('Microsoft token refresh failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $accessToken = (string) ($data['access_token'] ?? '');
        if ($accessToken === '') {
            return null;
        }

        $user->azure_access_token = $accessToken;
        if (! empty($data['refresh_token'])) {
            $user->azure_refresh_token = (string) $data['refresh_token'];
        }
        $user->azure_token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $user->save();

        return $accessToken;
    }
}
