# Testing Strategy: Accounting & General Ledger

To maintain strict compliance and absolute safety in all financial transactions, testing within this module follows a rigorous, isolation-first paradigm.

---

## 1. Priority Matrix (P0 - P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Accounts, Journal Entries, Ledger Lines, and Exchange Rates must be strictly scoped to `tenant_id`. No leakage across database boundaries. |
| **P0** | **Double-Entry Integrity** | Verification that unbalanced journal postings are rejected and transaction changes are rolled back. |
| **P1** | **Immutability** | Posted journal entries and ledger lines cannot be deleted or modified through direct API calls. |
| **P1** | **Atomicity & Drift** | Posting a journal entry must atomically update individual Account balances without data drift or double-counting. |
| **P2** | **Exchange Rates** | Validating currency conversion accuracy to six decimal points and verifying lowercase-to-uppercase auto-normalization. |
| **P2** | **Hierarchical Safety** | Ensuring circular parent-child loops in the Chart of Accounts (COA) are caught and prevented. |

---

## 2. Backend Testing (Pest PHP)

All backend tests must execute within the `erp_system_test` database context. The following blueprint illustrates how to write concrete Pest test cases for accounting:

### A. Multi-Tenant Data Isolation (P0)
Verify that Tenant A cannot query or mutate Tenant B's ledger accounts under any circumstances:

```php
it('enforces multi-tenant isolation on accounts', function () {
    // 1. Create a central tenant A and tenant B
    $tenantA = createTenant('tenant-a');
    $tenantB = createTenant('tenant-b');

    // 2. Seed an account in Tenant A
    tenancy()->initialize($tenantA);
    $accountA = Account::create([
        'code' => '1010',
        'name' => 'Cash A',
        'type' => 'asset',
    ]);

    // 3. Initialize Tenant B and attempt to retrieve Tenant A's account
    tenancy()->initialize($tenantB);
    
    // Assert: Tenant A's account is not found in Tenant B's scope
    expect(Account::find($accountA->id))->toBeNull();
    
    // Assert: Creating duplicate code '1010' in Tenant B is allowed (scope is separate)
    $accountB = Account::create([
        'code' => '1010',
        'name' => 'Cash B',
        'type' => 'asset',
    ]);
    expect($accountB->id)->not->toBe($accountA->id);
});
```

### B. Unbalanced Journal Entry Prevention (P0)
Verify that unbalanced journal lines fail validation and trigger a clean database rollback:

```php
it('rejects unbalanced journal entry postings', function () {
    $tenant = createTenant('my-tenant');
    tenancy()->initialize($tenant);

    $cash = Account::create(['code' => '1010', 'name' => 'Cash', 'type' => 'asset']);
    $expense = Account::create(['code' => '5400', 'name' => 'Rent', 'type' => 'expense']);

    $accountingService = app(AccountingService::class);

    // Unbalanced payload (Debits = 100, Credits = 150)
    $payload = [
        'reference_number' => 'JV-ERR-1',
        'description' => 'Unbalanced Rent Post',
        'entry_date' => now(),
        'lines' => [
            ['account_id' => $expense->id, 'debit' => 100.00, 'credit' => 0.00],
            ['account_id' => $cash->id, 'debit' => 0.00, 'credit' => 150.00],
        ]
    ];

    expect(fn() => $accountingService->postEntry($payload))
        ->toThrow(Exception::class, 'Unbalanced journal entry');

    // Assert: No journal entry header was persisted
    expect(JournalEntry::where('reference_number', 'JV-ERR-1')->exists())->toBeFalse();

    // Assert: Account balances were not altered (rollback succeeded)
    expect($cash->fresh()->balance)->toEqual(0.00);
});
```

### C. Immutability Enforcement (P1)
Ensure that once general ledger entries are written and posted, they are locked from deletion or mutation:

```php
it('prevents deletion or editing of posted journal entries', function () {
    $tenant = createTenant('my-tenant');
    tenancy()->initialize($tenant);

    $journal = JournalEntry::create([
        'reference_number' => 'JV-MUT-1',
        'status' => 'posted',
        'entry_date' => now(),
    ]);

    // Setup acting user with IAM credentials
    $user = createUserWithRole($tenant, 'finance-manager');

    // Act & Assert: Attempting to call DELETE on general ledger endpoint fails
    $response = $this->actingAs($user, 'api')
        ->withHeader('X-Tenant-Handle', $tenant->handle)
        ->deleteJson("/api/v1/fms/ledger/{$journal->id}");

    $response->assertStatus(403); // Forbidden
    expect(JournalEntry::find($journal->id))->not->toBeNull();
});
```

---

## 3. Postman Validation Rules

When updating or executing API collections under `docs/postman/`:
- **Header Injection**: Ensure `X-Tenant-Handle` and `Authorization: Bearer` are active on every request.
- **Precision Validation**: Assert that API JSON responses return decimal currency values as strings or precise decimals rather than native integers (e.g., `"125.00"` instead of `125`).
