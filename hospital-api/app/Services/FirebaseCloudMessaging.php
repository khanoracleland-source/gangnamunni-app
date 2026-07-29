<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseCloudMessaging
{
    private ?string $accessToken = null;

    public function sendToAndroid(string $token, array $payload): string
    {
        $projectId = (string) config('services.firebase.project_id');
        $credentials = (string) config('services.firebase.credentials');

        if ($projectId === '' || $credentials === '') {
            throw new RuntimeException('Firebase project ID or credentials path is not configured.');
        }

        if (! str_starts_with($credentials, DIRECTORY_SEPARATOR)) {
            $credentials = base_path($credentials);
        }

        if (! is_file($credentials) || ! is_readable($credentials)) {
            throw new RuntimeException('Firebase credentials file is not readable.');
        }

        $accessToken = $this->getAccessToken($credentials);

        $data = collect($payload['data'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) ? (string) $value : json_encode($value)])
            ->all();

        $message = [
            'message' => [
                'token' => $token,
                'notification' => array_filter([
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                    'image' => $payload['image_url'] ?? null,
                ]),
                'data' => $data,
                'android' => [
                    'priority' => ($payload['priority'] ?? 'normal') === 'high' ? 'HIGH' : 'NORMAL',
                    'notification' => [
                        'channel_id' => config('services.firebase.android_channel_id', 'general'),
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        $this->ensureSuccessful($response);

        return (string) $response->json('name');
    }

    private function getAccessToken(string $credentials): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentials);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $accessToken = $client->fetchAccessTokenWithAssertion();

        if (! isset($accessToken['access_token'])) {
            throw new RuntimeException(
                'Could not obtain a Firebase access token: '.($accessToken['error_description'] ?? 'unknown error')
            );
        }

        return $this->accessToken = $accessToken['access_token'];
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message') ?: $response->body();

        throw new RuntimeException("FCM request failed ({$response->status()}): {$message}");
    }
}
