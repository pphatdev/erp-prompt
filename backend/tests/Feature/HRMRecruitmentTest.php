<?php

namespace Tests\Feature;

use App\Models\Tenant\Application;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;

class HRMRecruitmentTest extends TenantTestCase
{
    /**
     * Test transition application to hired creates employee and links them.
     */
    public function test_transition_application_to_hired_creates_employee()
    {
        $vacancy = JobVacancy::create([
            'title' => 'Software Engineer',
            'status' => 'open',
            'vacancies_count' => 1,
            'employment_type' => 'full_time',
        ]);

        $application = Application::create([
            'job_vacancy_id' => $vacancy->id,
            'applicant_name' => 'John Doe',
            'applicant_email' => 'john.doe@test.com',
            'applicant_phone' => '1234567890',
            'expected_salary' => 5000.00,
            'status' => 'applied',
        ]);

        // Put application in the 'offer' state first to allow transitioning to 'hired'
        $application->update(['status' => 'offer']);

        $response = $this->tenantRequest('PATCH', "/api/v1/applications/{$application->id}/status", [
            'status' => 'hired',
        ]);

        $response->assertStatus(200);

        // Assert employee created
        $this->assertDatabaseHas('employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
            'status' => 'active',
        ]);

        $employee = Employee::where('email', 'john.doe@test.com')->first();
        $this->assertNotNull($employee);

        // Assert application has employee_id set
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'employee_id' => $employee->id,
            'status' => 'hired',
        ]);
    }

    /**
     * Test Employee policy self service view employee profile.
     */
    public function test_employee_policy_self_service_access()
    {
        // Create an employee-level role (e.g. employee) with NO hrm.employee.read permission
        $employeeRole = Role::create([
            'name' => 'Employee Role',
            'slug' => 'employee',
        ]);

        // Create user
        $user = User::create([
            'name' => 'Regular Employee User',
            'email' => 'emp@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach($employeeRole);

        // Create employee record linked to user
        $employee1 = Employee::create([
            'employee_id' => 'EMP-001',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'email' => 'emp@test.com',
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Create secondary employee
        $employee2 = Employee::create([
            'employee_id' => 'EMP-002',
            'first_name' => 'Another',
            'last_name' => 'Employee',
            'email' => 'another@test.com',
            'status' => 'active',
        ]);

        // 1. Regular employee tries to view index/list -> should fail (403)
        $this->actingAs($user, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->json('GET', '/api/v1/employees')
            ->assertStatus(403);

        // 2. Regular employee tries to view their own profile details -> should succeed (200)
        $this->actingAs($user, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->json('GET', "/api/v1/employees/{$employee1->id}")
            ->assertStatus(200)
            ->assertJsonPath('firstName', 'Regular')
            ->assertJsonPath('lastName', 'Employee');

        // 3. Regular employee tries to view another employee's profile -> should fail (403)
        $this->actingAs($user, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->json('GET', "/api/v1/employees/{$employee2->id}")
            ->assertStatus(403);
    }
}
