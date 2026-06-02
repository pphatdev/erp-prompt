<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\Ecommerce\Resources\EcomCustomerResource;
use App\Tenants\Modules\Ecommerce\Services\EcomCustomerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * Storefront shopper authentication. Uses Passport personal-access tokens
 * (created via the HasApiTokens trait on EcomCustomer) so the issued token
 * is verified against the `shop` guard, not the `api` (admin) guard.
 *
 * Tenant onboarding does NOT auto-run `passport:install`, so the
 * personal-access client + grant rows may be missing on a fresh tenant.
 * `ensurePersonalAccessClient()` self-heals the first time a register /
 * login hits this controller, then caches the row id in Passport's
 * static config so subsequent calls go through the fast path.
 */
class ShopperAuthController extends Controller
{
    public function __construct(private readonly EcomCustomerService $customers)
    {
    }

    /**
     * Make sure the tenant DB has a Passport personal-access client. Idempotent:
     * the first hit creates the client + the oauth_personal_access_clients
     * row pointing at it; subsequent hits short-circuit when one already
     * exists. Without this, `$customer->createToken('storefront')` throws
     * a cryptic "Attempt to read property 'map' on null" because Passport
     * tries to map over a null personal-access client config.
     */
    private function ensurePersonalAccessClient(): void
    {
        $exists = DB::table('oauth_personal_access_clients')->exists();
        if ($exists) {
            return;
        }

        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->create(
            null,
            'Storefront Personal Access Client',
            'http://localhost',
            'users',
            true,            // personal
            false,           // not password
            false            // not confidential
        );

        DB::table('oauth_personal_access_clients')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:255',
            'first_name' => 'sometimes|nullable|string|max:120',
            'last_name' => 'sometimes|nullable|string|max:120',
            'phone' => 'sometimes|nullable|string|max:40',
        ]);

        try {
            $customer = $this->customers->register($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->ensurePersonalAccessClient();
        $token = $customer->createToken('storefront');

        return response()->json([
            'customer' => (new EcomCustomerResource($customer->load('addresses')))->resolve($request),
            'token_type' => 'Bearer',
            'access_token' => $token->accessToken,
            'expires_at' => optional($token->token->expires_at)->toIso8601String(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = $this->customers->authenticate($credentials['email'], $credentials['password']);
        if (!$customer) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $this->ensurePersonalAccessClient();
        $token = $customer->createToken('storefront');

        return response()->json([
            'customer' => (new EcomCustomerResource($customer->load('addresses')))->resolve($request),
            'token_type' => 'Bearer',
            'access_token' => $token->accessToken,
            'expires_at' => optional($token->token->expires_at)->toIso8601String(),
        ]);
    }

    public function me(Request $request): EcomCustomerResource
    {
        return new EcomCustomerResource(Auth::guard('shop')->user()->load('addresses'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user('shop')?->token();
        if ($token) {
            $token->revoke();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
