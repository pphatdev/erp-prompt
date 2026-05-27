<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        // Identity
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'status',

        // Classification
        'customer_type',
        'external_code',
        'tier',

        // Business identifiers
        'tax_id',
        'industry',
        'website',

        // Structured billing address
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',

        // Locale
        'currency',
        'language',
        'timezone',

        // Account ownership
        'account_manager_id',

        // Notes
        'notes',

        // Branding (seeds new tenant's Settings on provisioning)
        'brand_primary_color',
        'brand_logo_url',

        // Tenant linkage (populated by TenantProvisioningService on Order::confirm)
        'tenant_handle',
        'provisioned_tenant_id',
        'provisioned_at',

        'tenant_id',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];

    /**
     * Drives provisioning behavior. Only `tenant`-typed customers get a
     * Central\Tenant provisioned when their software subscription confirms.
     */
    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_BUSINESS = 'business';
    public const TYPE_TENANT = 'tenant';
    public const TYPES = [self::TYPE_INDIVIDUAL, self::TYPE_BUSINESS, self::TYPE_TENANT];

    public const TIER_STANDARD = 'standard';
    public const TIER_PREMIUM = 'premium';
    public const TIER_ENTERPRISE = 'enterprise';
    public const TIERS = [self::TIER_STANDARD, self::TIER_PREMIUM, self::TIER_ENTERPRISE];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function isTenantCustomer(): bool
    {
        return $this->customer_type === self::TYPE_TENANT;
    }

    public function isProvisioned(): bool
    {
        return !empty($this->provisioned_tenant_id) && $this->provisioned_at !== null;
    }

    /**
     * Subdomain hostname (without scheme) where this customer accesses their
     * tenant — `{tenant_handle}.{platform.system_domain}`. Null until both
     * `tenant_handle` is set AND provisioning completed.
     */
    public function provisionedSubdomain(): ?string
    {
        if (!$this->tenant_handle || !$this->isProvisioned()) {
            return null;
        }
        return $this->tenant_handle . '.' . config('platform.system_domain', 'localhost');
    }

    /**
     * Full HTTPS URL the customer can click to enter their tenant. Returns
     * null until provisioning is complete. Used by Customer/Subscription
     * resources so any subscription-facing UI surfaces the live URL.
     */
    public function liveAccessUrl(): ?string
    {
        $subdomain = $this->provisionedSubdomain();
        return $subdomain ? 'https://' . $subdomain : null;
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
