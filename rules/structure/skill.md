---
name: erp-structural-implementation
description: Scaffold a new ERP module end-to-end (backend + frontend) so it matches the existing project's conventions. Use this when adding a new domain module, extending an existing one, or onboarding to the multi-tenant architecture.
---
# ERP Structural Implementation (Full-Stack Recipe)

This is the **canonical recipe** for adding a new feature end-to-end. Following it produces code shaped exactly like every other shipped module (Inventory Categories, Sales Orders, CRM Leads, ...). Deviating creates drift that future agents have to reverse-engineer.

**Reference reading** (read once, then use this file as the runbook):
- [`rules/structure/backend_structure.md`](./backend_structure.md) — directory map + namespaces
- [`rules/structure/frontend_structure.md`](./frontend_structure.md) — Nuxt layout + composables
- [`rules/tenancy/skill.md`](../tenancy/skill.md) — migration order + tenant header
- [`rules/auth/skill.md`](../auth/skill.md) — Passport + hashed-cast contract
- [`rules/frontend/ui_shell.md`](../frontend/ui_shell.md) — sidebar / dashboard / settings reproduction

---

## Worked example: Inventory Categories

The Inventory Categories feature is the **canonical reference** — it exercises every pattern in the codebase: self-referential FK, soft delete with guards, tree endpoint, recursive frontend rendering, policy + permission triple, sidebar entry under a module group. When in doubt, open these files and mirror them:

| Layer | File |
|---|---|
| Migration | `backend/database/migrations/tenant/{date}_create_inventory_tables.php` (categories block) |
| Model | `backend/app/Models/Tenant/Category.php` |
| Service | `backend/app/Tenants/Modules/Inventory/Services/CategoryService.php` |
| Policy | `backend/app/Policies/CategoryPolicy.php` |
| Controller | `backend/app/Tenants/Modules/Inventory/Controllers/CategoryController.php` |
| Resource | `backend/app/Tenants/Modules/Inventory/Resources/CategoryResource.php` |
| Route | `backend/routes/tenant.php` — `Route::apiResource('categories', CategoryController::class)` |
| Permission seeder | `backend/database/seeders/InventoryPermissionSeeder.php` |
| Page | `frontend/pages/inventory/categories.vue` |
| Composable wrapper | `frontend/composables/useInventory.ts` (categories block) |
| Sidebar entry | `frontend/layouts/default.vue` (Inventory nav group) |

---

## Backend chain (in order)

### Step 1 — Migration

`database/migrations/tenant/{YYYY_MM_DD}_create_{plural}_table.php`

- UUID primary key (`$table->uuid('id')->primary()`)
- Foreign keys to tenant scope (the `BelongsToTenant` trait reads `tenant_id` automatically)
- `$table->softDeletes()` on every business table
- Unique constraints on natural keys (slug, code, email)
- Indexes on `tenant_id` plus any column you'll filter on

**Self-FK gotcha (PostgreSQL):** split `Schema::create` + `Schema::table`:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('slug')->unique();
    $table->string('name');
    $table->uuid('parent_id')->nullable();
    $table->string('tenant_id');
    $table->softDeletes();
    $table->timestamps();
    $table->index('tenant_id');
});

Schema::table('categories', function (Blueprint $table) {
    $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
});
```

### Step 2 — Model

`app/Models/Tenant/Category.php` (NOT inside the module folder):

```php
namespace App\Models\Tenant;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['slug', 'name', 'description', 'color', 'sort_order', 'is_active', 'parent_id'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->id)) {
                $row->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($row->slug)) {
                $row->slug = \Illuminate\Support\Str::slug($row->name);
            }
        });
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function products() { return $this->hasMany(Product::class); }
}
```

### Step 3 — Service

`app/Tenants/Modules/Inventory/Services/CategoryService.php`. All business logic. Inject via constructor.

- Public methods describe domain verbs: `create`, `update`, `archive`, `tree`, `buildQuery`.
- Use `DB::transaction` for any multi-table write.
- Throw domain exceptions (`CycleDetectedException`, `HasDependentsException`) — not generic `\Exception`.
- For mutations, return the affected model — let the controller pass it to a Resource.
- **Don't** validate request shape here — that's the controller's job. Services validate **business invariants** (no cycles, no archive-with-children, etc.).

### Step 4 — Policy

`app/Policies/CategoryPolicy.php`. Standard five abilities: `viewAny`, `view`, `create`, `update`, `delete`.

```php
public function viewAny(User $user): bool { return $user->hasPermission('inventory.category.read'); }
public function create(User $user): bool { return $user->hasPermission('inventory.category.write'); }
public function update(User $user, Category $row): bool { return $user->hasPermission('inventory.category.write'); }
public function delete(User $user, Category $row): bool { return $user->hasPermission('inventory.category.delete'); }
```

For self-service variants, gate on `permission.read.self` AND `$user->employee_id === $row->employee_id`.

### Step 5 — Controller

`app/Tenants/Modules/Inventory/Controllers/CategoryController.php`. Thin — typically <120 lines for a full CRUD.

```php
public function __construct(private readonly CategoryService $service) {}

public function index(Request $request): JsonResponse|CategoryResource {
    Gate::authorize('viewAny', Category::class);
    if ($request->boolean('tree')) {
        return CategoryResource::collection($this->service->tree());
    }
    $paginator = $this->paginateQuery($this->service->buildQuery($request), $request);
    return $this->paginatedResponse(CategoryResource::class, $paginator, $request);
}

public function store(Request $request): CategoryResource {
    Gate::authorize('create', Category::class);
    $data = $this->validatePayload($request, isUpdate: false);
    return new CategoryResource($this->service->create($data));
}

public function update(Request $request, Category $category): CategoryResource {
    Gate::authorize('update', $category);
    $data = $this->validatePayload($request, isUpdate: true);
    return new CategoryResource($this->service->update($category, $data));
}

public function destroy(Category $category): JsonResponse {
    Gate::authorize('delete', $category);
    $this->service->archive($category);
    return response()->json(null, 204);
}
```

**Validation:** small CRUDs validate inline (`$request->validate([...])` or a private `validatePayload()` method). Reach for a dedicated `FormRequest` only when the rules grow past ~15 lines OR you need a non-trivial `authorize()` body.

**Response rule (P0):** never `response()->json(['data' => $resource->toArray($request)])`. Always return the `JsonResource` instance directly — calling `toArray()` bypasses the pipeline that filters `MissingValue` sentinels and produces `[object Object]` in JSON. For a 201, return `(new XxxResource(...))->response()->setStatusCode(201)`.

### Step 6 — Resource

`app/Tenants/Modules/Inventory/Resources/CategoryResource.php`. Output keys are **camelCase**:

```php
public function toArray(Request $request): array {
    return [
        'id'           => $this->id,
        'slug'         => $this->slug,
        'name'         => $this->name,
        'color'        => $this->color,
        'sortOrder'    => $this->sort_order,
        'isActive'     => $this->is_active,
        'parentId'     => $this->parent_id,
        'parent'       => CategoryResource::make($this->whenLoaded('parent')),
        'children'     => CategoryResource::collection($this->whenLoaded('children')),
        'productsCount'=> $this->when(isset($this->products_count), $this->products_count),
        'createdAt'    => $this->created_at,
        'updatedAt'    => $this->updated_at,
    ];
}
```

### Step 7 — Route

Append to the appropriate module-headed section in `routes/tenant.php`, **inside** the `auth:api` group:

```php
// Inventory
Route::apiResource('categories', CategoryController::class);
```

If you add custom routes that share a prefix with the resource (e.g. `GET /categories/tree`), declare them **before** the `apiResource`. Otherwise Laravel matches `tree` as the `{category}` parameter.

### Step 8 — Permission seeder

Create `database/seeders/InventoryPermissionSeeder.php` (or extend an existing one):

```php
$ids = [];
foreach ([
    ['name' => 'Read Categories',  'slug' => 'inventory.category.read',   'module' => 'inventory', 'feature' => 'category', 'action' => 'read'],
    ['name' => 'Write Categories', 'slug' => 'inventory.category.write',  'module' => 'inventory', 'feature' => 'category', 'action' => 'write'],
    ['name' => 'Delete Categories','slug' => 'inventory.category.delete', 'module' => 'inventory', 'feature' => 'category', 'action' => 'delete'],
] as $perm) {
    $ids[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
}
Role::where('slug', 'admin')->each(fn ($role) => $role->permissions()->syncWithoutDetaching($ids));
```

Then call it from `TenantDatabaseSeeder::run()` so new tenants get it automatically.

### Step 9 — Tests

`tests/Feature/Tenant/Inventory/CategoryCrudTest.php`. Minimum coverage:

- **P0 (Tenancy isolation)**: User in tenant A cannot read/write tenant B's rows.
- **P1 (Business invariants)**: Cycles rejected; archive blocked when children/products exist; soft-delete preserves history.
- **P2 (Audit)**: `audit_logs` (or current log channel) records every create/update/delete with actor + old/new values.

Always run against `erp_system_test` (enforced by `phpunit.xml`). Never the dev DB.

---

## Frontend chain (in order)

### Step 1 — Composable wrapper

Extend the relevant module composable (or create one). Example: `composables/useInventory.ts`:

```ts
categories: {
    list: (params) => api.get<Paginated<Category>>('/categories', { query: params }),
    tree: () => api.get<{ data: Category[] }>('/categories?tree=1'),
    create: (payload: CreateCategoryPayload) => api.post<{ data: Category }>('/categories', payload),
    update: (id: string, payload: UpdateCategoryPayload) => api.put<{ data: Category }>(`/categories/${id}`, payload),
    destroy: (id: string) => api.delete(`/categories/${id}`),
},
```

Types live in `types/inventory.ts`. Use the API response envelope (`{ data, pagination }`).

### Step 2 — Page

`pages/inventory/categories.vue`:

- `<script setup lang="ts">`, TypeScript strict, 4-space indent.
- `definePageMeta({ breadcrumb: 'Categories' })` — the layout reads this for the breadcrumb.
- Top of page: reactive form state, `canWrite`/`canDelete` computed from `authStore.hasPermission(...)`.
- Use the existing custom modal pattern (`fixed inset-0 bg-black/50 backdrop-blur-sm z-50 ...` wrapper, `.glass-card` body).
- Use `useToast()` for confirmations (`await toast.confirm({ ..., color: 'danger' })`) — never `window.confirm`.
- Format dates via `formatDate` / `formatDateTime` from `~/composables/useDateFormat`.
- For ≥ 2 row-actions, use the kebab pattern from `rules/frontend/standards.md` §7.

### Step 3 — Sidebar entry

Add a `NavItem` to the appropriate group in `layouts/default.vue` (search for the matching `groupId` such as `'apps'` and the matching `label` such as `'Inventory'`):

```ts
{
    label: 'Categories',
    icon: 'ti-category',
    route: '/inventory/categories',
    operational: true,
    permission: ['inventory.category.read', 'inventory.category.write'],
    moduleSlug: 'inventory',   // hidden if the tenant has disabled this module
}
```

- `permission` array = OR semantics (any one match unlocks).
- `moduleSlug` is checked via `useModules().hasModule(slug)` (fail-open if modules haven't loaded).
- If the item belongs in a new top-level group, define the group in the `navGroups` array.

### Step 4 — Optional: dashboard tile

If the new feature warrants a KPI on the dashboard, extend `DashboardSummaryService::summary()` (backend) and add a tile in `pages/dashboard.vue` reading from `summary?.kpis?.{namespace}?.{metric}`.

---

## Cross-module concerns

### Events, not direct calls

When the new module triggers behavior in another module (e.g. confirming an order reserves stock), use a domain event:

```php
event(new OrderConfirmed($order));        // dispatch AFTER the transaction commits
```

Register the listener in the consuming module's `Listeners/` folder. **Never** instantiate another module's service class directly from your service — that creates undocumented coupling.

### Multi-tenant data isolation (P0)

- Every model gets `use BelongsToTenant;`.
- Every test in `tests/Feature/Tenant/{Module}/` includes a TenancyIsolation case proving tenant A cannot see tenant B's rows.
- The frontend never needs to pass `tenant_id` in payloads — the backend reads it from the active connection (set by `InitializeTenancyByHandle` middleware from the `X-Tenant-Handle` header).

### File uploads

When the feature stores files: validate MIME server-side, sanitize filenames, store under `tenant_path()` (Stancl's tenant-scoped storage), and serve via short-lived signed URLs. See `rules/uploads/skill.md`.

---

## Pitfalls (don't repeat these)

| Symptom | Cause | Fix |
|---|---|---|
| `[object Object]` in returned JSON | Controller called `->toArray()` on a Resource | Return the Resource instance directly |
| `Hash::check()` always fails on a new user | Service called `Hash::make()` before assigning; the `'hashed'` cast double-hashes | Pass plaintext; let the cast hash once |
| Frontend can't see the new endpoint | Forgot `X-Tenant-Handle` injection | Always go through `useApi()` — never call `$fetch` directly |
| `relation "x" does not exist` after migration | Forgot `php artisan tenants:migrate` for the new tenant table | Run it, then `tenants:seed` |
| Self-FK migration fails on PostgreSQL | Single `Schema::create` with FK inline | Split into `Schema::create` + `Schema::table` |
| Sidebar item shows for everyone | Forgot the `permission` field on the NavItem | Add it; array = OR semantics |
| Sidebar item hidden even though user has permission | `moduleSlug` references a slug the tenant has disabled | Check `useModules().hasModule(slug)` |
| Custom route returns 404 | Declared after `apiResource` with overlapping path | Move it before the `apiResource` |
