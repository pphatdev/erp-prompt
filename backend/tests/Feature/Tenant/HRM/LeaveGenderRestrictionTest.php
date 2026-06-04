<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveType;
use App\Tenants\Modules\HRM\Services\LeaveService;
use DomainException;
use Tests\Feature\TenantTestCase;

/**
 * Gender-aware Leave Types.
 *
 * leave_types.applicable_gender ∈ {any, male, female}. When the type
 * restricts gender, the requesting employee's gender must match. `any`
 * (default) bypasses the check. Missing employee gender + restricted type
 * blocks the request explicitly so HR can fix the profile.
 */
class LeaveGenderRestrictionTest extends TenantTestCase
{
    private LeaveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaveService::class);
    }

    public function test_female_only_type_blocks_male_employee(): void
    {
        [$employee, $type] = $this->seed('male', 'female');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('restricted to female');

        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04',
            'end_date'      => '2030-03-04',
            'reason'        => 'should not be allowed',
        ]);
    }

    public function test_female_only_type_allows_female_employee(): void
    {
        [$employee, $type] = $this->seed('female', 'female');

        $leave = $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04',
            'end_date'      => '2030-03-04',
            'reason'        => 'maternity prep',
        ]);

        $this->assertNotNull($leave);
    }

    public function test_male_only_type_blocks_female_employee(): void
    {
        [$employee, $type] = $this->seed('female', 'male');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('restricted to male');

        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04',
            'end_date'      => '2030-03-04',
            'reason'        => 'should not be allowed',
        ]);
    }

    public function test_any_type_allows_unset_employee_gender(): void
    {
        [$employee, $type] = $this->seed(null, 'any');

        $leave = $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04',
            'end_date'      => '2030-03-04',
            'reason'        => 'general',
        ]);

        $this->assertNotNull($leave);
    }

    public function test_restricted_type_blocks_employee_without_gender(): void
    {
        [$employee, $type] = $this->seed(null, 'female');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no gender on file');

        $this->service->submitRequest([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'start_date'    => '2030-03-04',
            'end_date'      => '2030-03-04',
            'reason'        => 'profile missing gender',
        ]);
    }

    /**
     * @return array{0: Employee, 1: LeaveType}
     */
    private function seed(?string $employeeGender, string $applicable): array
    {
        $employee = Employee::create([
            'employee_id' => 'EMP-GENDER-' . strtoupper(bin2hex(random_bytes(2))),
            'first_name'  => 'Gee',
            'last_name'   => 'Tester',
            'email'       => 'gee.' . bin2hex(random_bytes(3)) . '@example.test',
            'gender'      => $employeeGender,
            'hired_at'    => '2025-01-01',
            'status'      => 'active',
        ]);

        $type = LeaveType::create([
            'name'              => 'Type-' . $applicable,
            'annual_allowance'  => 30,
            'applicable_gender' => $applicable,
        ]);

        return [$employee, $type];
    }
}
