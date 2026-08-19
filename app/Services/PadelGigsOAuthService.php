<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PadelGigsOAuthService
{
    protected string $baseUrl;

    protected ?string $clientId;

    protected ?string $clientSecret;

    protected ?string $redirectUri;

    public function __construct()
    {
        $this->baseUrl = config('services.padelgigs.url', 'https://padelgigs.id');
        $this->clientId = config('services.padelgigs.client_id');
        $this->clientSecret = config('services.padelgigs.client_secret');
        $this->redirectUri = config('services.padelgigs.redirect');
    }

    /**
     * Generate authorization URL with PKCE
     */
    public function getAuthorizationUrl(array $scopes = ['openid', 'profile', 'email']): array
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Store in session for verification
        session([
            'padelgigs_oauth_state' => $state,
            'padelgigs_oauth_code_verifier' => $codeVerifier,
        ]);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return [
            'url' => "{$this->baseUrl}/oauth/authorize?{$query}",
            'state' => $state,
        ];
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code, string $state): array
    {
        // Verify state
        $storedState = session('padelgigs_oauth_state');
        if (! $storedState || $state !== $storedState) {
            throw new \RuntimeException('Sesi autentikasi SSO tidak valid (Invalid OAuth state).');
        }

        $codeVerifier = session('padelgigs_oauth_code_verifier');

        // Clear session data
        session()->forget(['padelgigs_oauth_state', 'padelgigs_oauth_code_verifier']);

        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gagal menukar kode otorisasi dengan token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'scope' => 'openid profile email',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gagal memperbarui token SSO: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get user info from PadelGigs
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("{$this->baseUrl}/api/user");

        if ($response->failed()) {
            throw new \RuntimeException('Gagal mengambil data profil dari PadelGigs: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get valid access token for a user (refresh if needed)
     */
    public function getValidAccessToken(User $user): ?string
    {
        if (! $user->isLinkedToPadelgigs()) {
            return null;
        }

        if (! $user->isPadelgigsTokenExpired()) {
            return $user->padelgigs_access_token;
        }

        if (! $user->padelgigs_refresh_token) {
            return null;
        }

        try {
            $tokens = $this->refreshToken($user->padelgigs_refresh_token);

            $user->update([
                'padelgigs_access_token' => $tokens['access_token'],
                'padelgigs_refresh_token' => $tokens['refresh_token'] ?? $user->padelgigs_refresh_token,
                'padelgigs_token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            ]);

            return $tokens['access_token'];
        } catch (\Throwable $e) {
            $user->update([
                'padelgigs_access_token' => null,
                'padelgigs_refresh_token' => null,
                'padelgigs_token_expires_at' => null,
            ]);

            return null;
        }
    }

    /**
     * Generate PKCE code challenge
     */
    protected function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
