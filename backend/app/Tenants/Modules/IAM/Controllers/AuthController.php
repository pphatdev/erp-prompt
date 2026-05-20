<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Tenants\Modules\IAM\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\RefreshTokenRepository;
use Laravel\Passport\TokenRepository;

class AuthController extends Controller
{
    /**
     * Authenticate via Passport Password Grant.
     *
     * Returns access_token + refresh_token + expires_in alongside the user
     * profile, matching the OAuth2 contract documented in
     * rules/auth/auth_standards.md §2.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $tokens = $this->issueTokenViaPassport([
            'grant_type'    => 'password',
            'username'      => $credentials['email'],
            'password'      => $credentials['password'],
            'scope'         => '*',
        ]);

        if (!isset($tokens['access_token'])) {
            return response()->json([
                'message' => $tokens['message'] ?? 'Invalid credentials.',
            ], $tokens['status'] ?? 401);
        }

        return response()->json([
            'user'          => (new UserResource($user->load('roles.permissions')))->toArray($request),
            'token_type'    => $tokens['token_type'] ?? 'Bearer',
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_in'    => $tokens['expires_in'] ?? null,
        ]);
    }

    /**
     * Exchange a refresh_token for a fresh access_token + rotated refresh_token.
     *
     * Intentionally NOT behind `auth:api` — the access token has expired by the
     * time clients hit this endpoint.
     */
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $tokens = $this->issueTokenViaPassport([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $data['refresh_token'],
            'scope'         => '*',
        ]);

        if (!isset($tokens['access_token'])) {
            return response()->json([
                'message' => $tokens['message'] ?? 'Invalid or expired refresh token.',
            ], $tokens['status'] ?? 401);
        }

        return response()->json([
            'token_type'    => $tokens['token_type'] ?? 'Bearer',
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_in'    => $tokens['expires_in'] ?? null,
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(Auth::user()->load('roles.permissions'));
    }

    /**
     * Revoke the active access token and its refresh-token chain.
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenInstance = $request->user()->token();

        if ($tokenInstance) {
            app(RefreshTokenRepository::class)->revokeRefreshTokensByAccessTokenId($tokenInstance->id);
            app(TokenRepository::class)->revokeAccessToken($tokenInstance->id);
        }

        return response()->json(['message' => 'Successfully logged out.']);
    }

    /**
     * Forward a token request through Passport's /oauth/token endpoint.
     * Keeps the tenant DB connection (already initialised by middleware) intact.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function issueTokenViaPassport(array $payload): array
    {
        $clientId     = config('passport.password_client_id');
        $clientSecret = config('passport.password_client_secret');

        if (!$clientId || !$clientSecret) {
            return [
                'status'  => 500,
                'message' => 'Passport password client is not configured. Run `php artisan tenants:run passport:install-password-client` and set PASSPORT_PASSWORD_CLIENT_ID / PASSPORT_PASSWORD_CLIENT_SECRET in your .env.',
            ];
        }

        $tokenRequest = Request::create('/oauth/token', 'POST', array_merge($payload, [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]));
        $tokenRequest->headers->set('Accept', 'application/json');

        $response = app()->handle($tokenRequest);
        $decoded = json_decode($response->getContent(), true) ?: [];
        $decoded['status'] = $response->getStatusCode();

        return $decoded;
    }
}
