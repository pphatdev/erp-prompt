# Employee Role & Self-Service — Testing Strategy

Use this testing strategy to verify that the Employee system role and its
associated role-mail login are functioning securely, properly isolated, and scoped
correctly at both API and Database levels.

---

## 1. Priority Matrix (P0 - P2)

| Priority | Category | Requirement / Test Case |
|---|---|---|
| **P0** | **Access Isolation** | Employee user MUST get a `403 Forbidden` if accessing another employee's records or general employee list. |
| **P0** | **Authentication** | Valid credentials for `employee.NN@<handle>.com` must return a Bearer Token; wrong credentials must fail. |
| **P1** | **RBAC Scope** | Verify that `hrm.leave.read` permits reading own leaves and denies list read of other employees' leaves. |
| **P2** | **Audit Logging** | Verify that sensitive actions (viewing payslips, updating own profile) create audit trail records. |

---

## 2. Backend Testing (Pest PHP)

Place these tests in `tests/Feature/Tenants/Modules/IAM/EmployeeRoleTest.php`.

### 2.1 Database Isolation & Authentication (P0)

Verify that the employee role-mail login works correctly within the active tenant connection.

```php
use App\Models\Tenant\User;
use App\Models\Tenant\Role;

it('allows employee to authenticate via role-mail login', function () {
    // 1. Establish tenant context
    $tenant = tenant();
    $handle = $tenant->id;
    $email = "employee.01@{$handle}.com";

    // 2. Perform OAuth request simulated via Post
    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => $email,
        'password' => 'tt@126$Kh#',
    ], [
        'X-Tenant-Handle' => $handle
    ]);

    // 3. Assert correct JWT structure and roles returned
    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => [
                'id',
                'email',
                'roles',
            ]
        ]);

    expect($response->json('user.email'))->toBe($email);
});
```

### 2.2 Ownership Scoping & 403 Guards (P0)

Ensure the employee cannot access other users' employee models.

```php
use App\Models\Tenant\Employee;

it('blocks employee from viewing other employee profiles', function () {
    // 1. Arrange: Create two employee records and users
    $employeeUser = User::factory()->create();
    $employeeRole = Role::where('slug', 'employee')->first();
    $employeeUser->roles()->attach($employeeRole);

    $myEmployeeRecord = Employee::factory()->create(['user_id' => $employeeUser->id]);
    $otherEmployeeRecord = Employee::factory()->create();

    // 2. Act & Assert: Attempt to read other employee record
    $this->actingAs($employeeUser, 'api')
        ->getJson("/api/v1/employees/{$otherEmployeeRecord->id}")
        ->assertStatus(403);

    // 3. Act & Assert: Attempt to read own employee record
    $this->actingAs($employeeUser, 'api')
        ->getJson("/api/v1/employees/{$myEmployeeRecord->id}")
        ->assertOk();
});

it('blocks employee from listing all employee profiles', function () {
    $employeeUser = User::factory()->create();
    $employeeRole = Role::where('slug', 'employee')->first();
    $employeeUser->roles()->attach($employeeRole);

    $this->actingAs($employeeUser, 'api')
        ->getJson('/api/v1/employees')
        ->assertStatus(403);
});
```

### 2.3 Self-Service Feature RBAC (P1)

Verify that the employee can request leave (write) and view their own leave requests (read).

```php
use App\Models\Tenant\Leave;

it('allows employee to read and write their own leave requests', function () {
    $employeeUser = User::factory()->create();
    $employeeRole = Role::where('slug', 'employee')->first();
    $employeeUser->roles()->attach($employeeRole);

    $myEmployeeRecord = Employee::factory()->create(['user_id' => $employeeUser->id]);

    // 1. Write Leave Request
    $writeResponse = $this->actingAs($employeeUser, 'api')
        ->postJson('/api/v1/hrm/leaves', [
            'employee_id' => $myEmployeeRecord->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'type' => 'annual',
            'reason' => 'Annual vacation',
        ]);

    $writeResponse->assertCreated();

    // 2. Read Leave Request
    $this->actingAs($employeeUser, 'api')
        ->getJson('/api/v1/hrm/leaves')
        ->assertOk()
        ->assertJsonFragment(['reason' => 'Annual vacation']);
});
```

### 2.4 Audit Logging Check (P2)

Confirm that an audit entry is created when accessing or modifying personal data.

```php
it('creates audit log entries on sensitive employee interactions', function () {
    $employeeUser = User::factory()->create();
    $employeeRole = Role::where('slug', 'employee')->first();
    $employeeUser->roles()->attach($employeeRole);

    $myEmployee = Employee::factory()->create(['user_id' => $employeeUser->id]);

    // Perform profile view or update
    $this->actingAs($employeeUser, 'api')
        ->getJson("/api/v1/employees/{$myEmployee->id}");

    // Assert that the audit log recorded this read event
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $employeeUser->id,
        'action' => 'employee.profile.viewed',
        'auditable_type' => Employee::class,
        'auditable_id' => $myEmployee->id,
    ]);
});
```

---

## 3. Postman Validation Cases

Add the following verification test script to your login request under `docs/postman/erp_collection.json`:

```javascript
// Postman Pre-request / Tests script
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Token details parsed and roles confirm Employee", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData.access_token).to.not.be.undefined;
    
    // Capture token and store inside collection variables
    pm.collectionVariables.set("token", jsonData.access_token);
    
    // Assert the role is exactly 'employee'
    var roles = jsonData.user.roles.map(r => r.slug);
    pm.expect(roles).to.include("employee");
});
```
