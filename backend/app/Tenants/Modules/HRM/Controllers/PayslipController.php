<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Payslip;
use App\Tenants\Modules\HRM\Resources\PayslipResource;
use App\Tenants\Modules\Settings\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayslipController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payslip::class);

        $query = Payslip::query()->with(['employee', 'payrollPeriod']);

        $user = $request->user();
        $isAdmin = $user?->hasPermission('hrm.payroll.read');

        // Self-service caller (`.self` only) — force-filter to their own
        // employee_id so they can't enumerate other employees' payslips by
        // dropping or rewriting the `employeeId` query param.
        if (!$isAdmin) {
            $selfId = $user?->employee?->id;
            if ($selfId === null) {
                return response()->json(['data' => [], 'pagination' => ['page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0]], 200);
            }
            $query->where('employee_id', $selfId);
        } elseif ($employeeId = $request->query('employeeId')) {
            $query->where('employee_id', $employeeId);
        }

        if ($periodId = $request->query('payrollPeriodId')) {
            $query->where('payroll_period_id', $periodId);
        }

        $paginator = $this->paginateQuery($query->orderBy('created_at', 'desc'), $request);

        return $this->paginatedResponse(PayslipResource::class, $paginator, $request);
    }

    public function show(Payslip $payslip): PayslipResource
    {
        $this->authorize('view', $payslip);

        return new PayslipResource($payslip->load(['employee', 'payrollPeriod']));
    }

    /**
     * ESS portal: list the authenticated employee's own payslips.
     *
     * Mirrors {@see self::index()} but force-scopes to the caller's linked
     * employee_id without consulting `hrm.payroll.read`. Returns 404 when
     * the user has no employee row (admin / external accounts) so the
     * frontend can distinguish "no payslips yet" (200 with empty list)
     * from "no employee record" (404).
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $employeeId = $user?->employee?->id;

        if ($employeeId === null) {
            return response()->json(['message' => 'No employee record linked to this account.'], 404);
        }

        if (!$user->hasPermission('hrm.payslip.read.self') && !$user->hasPermission('hrm.payroll.read')) {
            return response()->json(['message' => 'You do not have permission to read payslips.'], 403);
        }

        $query = Payslip::query()
            ->with(['employee', 'payrollPeriod'])
            ->where('employee_id', $employeeId);

        if ($periodId = $request->query('payrollPeriodId')) {
            $query->where('payroll_period_id', $periodId);
        }

        $paginator = $this->paginateQuery($query->orderBy('created_at', 'desc'), $request);

        return $this->paginatedResponse(PayslipResource::class, $paginator, $request);
    }

    /**
     * Stream a single payslip as application/pdf.
     *
     * Gated by the same {@see PayslipPolicy::view()} rules as JSON show — an
     * employee can download their own; admin/HR with `hrm.payroll.read` can
     * download anyone's. The PDF is generated server-side so the document
     * always renders identically across browsers and so the resource can be
     * attached to emails / archived without round-tripping through the UI.
     */
    public function pdf(Request $request, Payslip $payslip, SettingService $settings): Response
    {
        $this->authorize('view', $payslip);

        $payslip->load(['employee', 'payrollPeriod']);

        $earnings = is_array($payslip->earnings) ? $payslip->earnings : [];
        $deductions = is_array($payslip->deductions) ? $payslip->deductions : [];

        $tenantName = tenant()?->name ?? config('app.name', 'ERP');
        $currency = $settings->get('locale.currency') ?: 'USD';
        if (!is_string($currency)) {
            $currency = 'USD';
        }

        $labels = [
            'base'         => 'Base salary',
            'bonus'        => 'Bonus',
            'overtime'     => 'Overtime',
            'tax'          => 'Income tax',
            'nssf'         => 'Social security (NSSF)',
            'absent'       => 'Absence deduction',
            'unpaid_leave' => 'Unpaid leave',
        ];

        $pdf = Pdf::loadView('hrm.payslip-pdf', [
            'payslip'     => $payslip,
            'employee'    => $payslip->employee,
            'period'      => $payslip->payrollPeriod,
            'earnings'    => $earnings,
            'deductions'  => $deductions,
            'labels'      => $labels,
            'currency'    => $currency,
            'tenantName'  => $tenantName,
            'generatedAt' => now()->toIso8601String(),
        ]);

        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payslip->payrollPeriod?->name ?? 'period',
            substr($payslip->id, 0, 8),
        );

        return $pdf->download($filename);
    }
}
