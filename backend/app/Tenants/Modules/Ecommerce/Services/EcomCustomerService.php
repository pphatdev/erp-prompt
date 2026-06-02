<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Services;

use App\Models\Tenant\EcomAddress;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\Role;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Shopper account lifecycle: register, login probe, address book CRUD.
 *
 * Auth tokens themselves are minted in the controller layer via Passport's
 * `createToken()` — keeping this service framework-agnostic.
 */
class EcomCustomerService
{
    /**
     * Create a registered shopper. Password is trusted to the `'hashed'` cast
     * on EcomCustomer — never call Hash::make() here.
     */
    public function register(array $data): EcomCustomer
    {
        if (EcomCustomer::where('email', $data['email'])->exists()) {
            throw new DomainException("Email '{$data['email']}' is already registered.");
        }

        return DB::transaction(function () use ($data) {
            return EcomCustomer::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_guest' => false,
                'is_active' => true,
            ])->fresh();
        });
    }

    /**
     * Create a one-shot guest record. Used by guest-checkout flow so the order
     * has a customer to belong to without forcing account creation.
     */
    public function createGuest(string $email, ?string $firstName = null, ?string $lastName = null): EcomCustomer
    {
        return EcomCustomer::create([
            'email' => $email,
            'password' => 'guest-' . bin2hex(random_bytes(16)),  // unreachable, never logs in
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_guest' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Verify credentials. Returns the matching customer or null.
     */
    public function authenticate(string $email, string $password): ?EcomCustomer
    {
        $customer = EcomCustomer::where('email', $email)
            ->where('is_guest', false)
            ->where('is_active', true)
            ->first();
        if (!$customer) {
            return null;
        }
        if (!Hash::check($password, $customer->password)) {
            return null;
        }
        $customer->update(['last_login_at' => now()]);
        return $customer;
    }

    public function addAddress(EcomCustomer $customer, array $data): EcomAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            if (!empty($data['is_default_shipping'])) {
                $customer->addresses()->update(['is_default_shipping' => false]);
            }
            if (!empty($data['is_default_billing'])) {
                $customer->addresses()->update(['is_default_billing' => false]);
            }

            return $customer->addresses()->create([
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'] ?? null,
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => strtoupper($data['country']),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
            ]);
        });
    }

    public function updateAddress(EcomAddress $address, array $data): EcomAddress
    {
        return DB::transaction(function () use ($address, $data) {
            if (!empty($data['is_default_shipping'])) {
                $address->customer->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default_shipping' => false]);
            }
            if (!empty($data['is_default_billing'])) {
                $address->customer->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default_billing' => false]);
            }

            $address->update(array_filter([
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'line1' => $data['line1'] ?? null,
                'line2' => $data['line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => isset($data['country']) ? strtoupper($data['country']) : null,
                'is_default_shipping' => $data['is_default_shipping'] ?? null,
                'is_default_billing' => $data['is_default_billing'] ?? null,
            ], static fn ($v) => $v !== null));

            return $address->fresh();
        });
    }

    public function deleteAddress(EcomAddress $address): void
    {
        $address->delete();
    }
}
