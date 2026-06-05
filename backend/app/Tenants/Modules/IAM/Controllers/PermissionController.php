<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Permission;
use App\Tenants\Modules\IAM\Resources\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

/**
 * Permission catalogue surface. Read-only — permissions are seeded
 * idempotently by `TenantDatabaseSeeder` and are not user-creatable.
 *
 * Returns the full catalogue without pagination because the roles
 * matrix UI groups by module → feature → action and needs every row at
 * once to render correctly. With ~150 rows the payload is well under
 * any practical limit.
 */
class PermissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Permission::query()->orderBy('module')->orderBy('feature')->orderBy('action');

        if ($module = $request->query('module')) {
            $query->where('module', $module);
        }

        return PermissionResource::collection($query->get());
    }
}
