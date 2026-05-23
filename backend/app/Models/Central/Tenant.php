<?php

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * `handle` is the primary key — human-readable, URL-safe, and unique.
     * Stancl derives the tenant database name from getTenantKey(), which
     * returns this value, so databases are named `tenant_{handle}`.
     */
    protected $primaryKey = 'handle';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'handle',
        'name',
        'data',
    ];

    /**
     * Tell Stancl which column is the tenant identifier. This is used
     * internally for tenant resolution, database naming, and event payloads.
     */
    public function getTenantKeyName(): string
    {
        return 'handle';
    }

    public static function getCustomColumns(): array
    {
        return [
            'handle',
            'name',
        ];
    }
}
