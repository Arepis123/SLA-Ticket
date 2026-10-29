<?php

namespace App\Services\Push;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through Firebase Cloud Messaging's HTTP v1 API, signed in
 * with the Firebase project's service account.
 */
class FcmClient
{
    protected const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null */
    protected ?array $credentials = null;

    public function __construct(protected ?string $credentialsPath) {}

    /**
     * Whether a service account file is in place. Without one, pushes are
     * skipped and the rest of the app works as before.
     */
    public function configured(): bool
    {
        return filled($this->credentialsPath) && is_file($this->credentialsPath);
    }

    /**
     * Send one message to one device.
     *
     * @param  array<string, mixed>  $message  The FCM message, without the token.
     */
    public function send(string $token, array $message): FcmResult
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->credentials()['project_id']}/messages:send", [
                    'message' => ['token' => $token, ...$message],
                ]);
        } catch (ConnectionException) {
            return FcmResult::Failed;
        }

        return match (true) {
            $response->successful() => FcmResult::Sent,
            $this->isDeadToken($response) => FcmResult::DeadToken,
            default => FcmResult::Failed,
        };
    }

    /**
     * The app was uninstalled, or the token was replaced or never valid.
     */
    protected function isDeadToken(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        $details = $response->json('error.details');
        $codes = is_array($details) ? array_column($details, 'errorCode') : [];

        return in_array('UNREGISTERED', $codes, true)
            || ($response->status() === 400 && in_array('INVALID_ARGUMENT', $codes, true) && str_contains((string) $response->json('error.message'), 'registration token'));
    }

    /**
     * Google access tokens last an hour; reuse one until shortly before then.
     */
    protected function accessToken(): string
    {
        return Cache::remember('fcm-access-token', now()->addMinutes(50), function () {
            $token = (new ServiceAccountCredentials(self::SCOPE, $this->credentials()))->fetchAuthToken();

            return (string) $token['access_token'];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return $this->credentials ??= json_decode((string) file_get_contents((string) $this->credentialsPath), true, flags: JSON_THROW_ON_ERROR);
    }
}
