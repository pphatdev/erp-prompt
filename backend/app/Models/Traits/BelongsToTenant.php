<?php

namespace App\Models\Traits;

use Stancl\Tenancy\Database\Concerns\BelongsToTenant as BaseBelongsToTenant;

/**
 * Custom wrapper for the package's BelongsToTenant trait.
 * Ensures all tenant-scoped models are automatically filtered by the current tenant context.
 */
trait BelongsToTenant
{
    use BaseBelongsToTenant;
}
