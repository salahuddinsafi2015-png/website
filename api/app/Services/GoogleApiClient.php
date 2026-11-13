<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Google service-account client.
 *
 * Signs a JWT with the private key from the credentials JSON, exchanges it for
 * an OAuth access token, and caches that token until shortly before it
 * expires. No Google SDK is required.
 */
class GoogleApiClient
{
    public const ANALYTICS_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public const ADSENSE_SCOPE = 'https://www.googleapis.com/auth/adsense.readonly';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    public function credentials(): ?array
    {
        $raw = Setting::get('analytics.credentials_json');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])) {
            return null;
        }

        return [
            'client_email' => (string) $decoded['client_email'],
            'private_key' => (string) $decoded['private_key'],
        ];
    }

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    public function token(string $scope): string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            throw new RuntimeException('Google service account credentials are not configured.');
        }

        $cacheKey = 'google.token.'.sha1($credentials['client_email'].'|'.$scope);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials, $scope): string {
            $response = Http::asForm()
                ->timeout(20)
                ->post(self::TOKEN_ENDPOINT, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->jwt($credentials['client_email'], $credentials['private_key'], $scope),
                ]);

            if ($response->failed() || ! $response->json('access_token')) {
                throw new RuntimeException(
                    'Google rejected the service account credentials ('.$response->status().'). '
                    .($response->json('error_description') ?: 'Check that the key is a service account JSON key.'),
                );
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $body, string $scope): array
    {
        $response = Http::withToken($this->token($scope))
            ->timeout(25)
            ->post($url, $body);

        return $this->decode($response->status(), $response->json(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $url, array $query, string $scope): array
    {
        $response = Http::withToken($this->token($scope))
            ->timeout(25)
            ->get($url, $query);

        return $this->decode($response->status(), $response->json(), $response->body());
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function decode(int $status, ?array $json, string $raw): array
    {
        if ($status < 200 || $status >= 300) {
            $message = $json['error']['message'] ?? $json['error_description'] ?? 'request failed';

            throw new RuntimeException("Google API error ({$status}): {$message}");
        }

        if (! is_array($json)) {
            throw new RuntimeException('Google API returned an unreadable response.');
        }

        return $json;
    }

    private function jwt(string $email, string $privateKey, string $scope): string
    {
        $issuedAt = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $email,
            'scope' => $scope,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $unsigned = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));

        $signature = '';

        try {
            $signed = @openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        } catch (\Throwable) {
            $signed = false;
        }

        if (! $signed || $signature === '' || $signature === false) {
            throw new RuntimeException('The service account private key could not sign a token.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
