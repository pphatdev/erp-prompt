<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;

/**
 * Storefront shopper. Authenticatable so Passport password grant works against
 * this table via a separate `shop` guard (configured in `config/auth.php`).
 *
 * Distinct from `App\Models\Tenant\User` (admin) and `App\Models\Tenant\Customer`
 * (B2B Sales partner). `is_guest` flags one-shot checkouts so admin reports can
 * separate registered shoppers from anonymous buyers.
 */
class EcomCustomer extends Authenticatable
{
    use HasApiTokens, Notifiable, BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_customers';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'is_guest',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'phone' => 'encrypted',
        'is_guest' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Shoppers do NOT participate in the admin RBAC matrix - the
     * `user_has_roles` pivot's FK targets `users.id`, not `ecom_customers.id`.
     * Access for the storefront is gated by the `shop` Passport guard, not
     * by role rows. Method kept (returning an empty relation against an
     * impossible row) so any legacy `->roles()` chain still type-checks
     * without throwing.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_roles', 'user_id', 'role_id')
            ->whereRaw('1 = 0');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(EcomAddress::class, 'customer_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(EcomCart::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EcomOrder::class, 'customer_id');
    }

    public function defaultShipping(): ?EcomAddress
    {
        return $this->addresses()->where('is_default_shipping', true)->first();
    }

    public function defaultBilling(): ?EcomAddress
    {
        return $this->addresses()->where('is_default_billing', true)->first();
    }

    /**
     * Shoppers are not part of the admin permission catalog. The shop guard
     * grants the implicit `ecommerce.storefront.read` capability to every
     * authenticated EcomCustomer; everything else returns false so the
     * admin policies don't accidentally grant storefront users elevated
     * access if they're ever passed through the same hasPermission()
     * surface area.
     */
    public function hasPermission(string $permission): bool
    {
        return $permission === 'ecommerce.storefront.read';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
