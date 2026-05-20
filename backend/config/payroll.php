<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chart-of-Accounts mapping for the payroll accrual journal
    |--------------------------------------------------------------------------
    |
    | PayrollService::closePeriod() looks up these accounts (by `code`) in the
    | per-tenant chart of accounts and posts a balanced journal entry. If any
    | of the four codes is missing for the current tenant, the close fails
    | with a 422 listing the missing codes — tenants must create them in FMS
    | before payroll can be closed.
    |
    | Codes are configurable per environment so tenants standardised on
    | another chart (e.g. country-specific GAAP) can override without code
    | changes. The defaults align with the seed COA shipped under
    | TenantDatabaseSeeder.
    */
    'accounts' => [
        'wage_expense'  => env('PAYROLL_ACCOUNT_WAGE_EXPENSE', 'EXP-WAGES'),
        'tax_payable'   => env('PAYROLL_ACCOUNT_TAX_PAYABLE', 'LIA-TAX'),
        'nssf_payable'  => env('PAYROLL_ACCOUNT_NSSF_PAYABLE', 'LIA-NSSF'),
        'wages_payable' => env('PAYROLL_ACCOUNT_WAGES_PAYABLE', 'LIA-WAGES'),
    ],
];
