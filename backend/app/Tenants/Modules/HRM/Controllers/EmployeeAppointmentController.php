<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EmployeeAppointment;
use App\Tenants\Modules\HRM\Requests\StoreEmployeeAppointmentRequest;
use App\Tenants\Modules\HRM\Resources\EmployeeAppointmentResource;
use App\Tenants\Modules\HRM\Services\EmployeeAppointmentService;
use DomainException;
use Illuminate\Http\JsonResponse;

class EmployeeAppointmentController extends Controller
{
    public function __construct(private readonly EmployeeAppointmentService $appointments)
    {
    }

    public function store(StoreEmployeeAppointmentRequest $request): EmployeeAppointmentResource|JsonResponse
    {
        if (!$request->user()->hasPermission('hrm.recruitment.write')) {
            abort(403, 'Missing hrm.recruitment.write permission.');
        }

        try {
            $appointment = $this->appointments->submit($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EmployeeAppointmentResource(
            $appointment->load(['department', 'position', 'manager', 'application'])
        );
    }

    public function show(\Illuminate\Http\Request $request, EmployeeAppointment $appointment): EmployeeAppointmentResource
    {
        $user = $request->user();

        $isOwner = $appointment->submitted_by === $user?->id;
        $canRead = $user?->hasPermission('hrm.recruitment.read')
            || $user?->hasPermission('approvals.requests.read');

        if (!$isOwner && !$canRead) {
            abort(403, 'Unauthorized to view this appointment request.');
        }

        return new EmployeeAppointmentResource(
            $appointment->load(['department', 'position', 'manager', 'application'])
        );
    }
}
