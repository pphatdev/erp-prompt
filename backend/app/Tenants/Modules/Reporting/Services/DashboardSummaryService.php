<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Reporting\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Leave;
use App\Models\Tenant\Module;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    public function build(User $user): array
    {
        $cacheKey = 'tenant_' . tenant('id') . '_dashboard_summary_' . $user->id;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $now       = Carbon::now();
            $monthStart = $now->copy()->startOfMonth();
            $prevStart  = $now->copy()->subMonth()->startOfMonth();
            $prevEnd    = $now->copy()->subMonth()->endOfMonth();

            $activeSlugs = Module::where('is_active', true)->pluck('slug')->all();

            return [
                'period'               => [
                    'start' => $monthStart->toDateString(),
                    'end'   => $now->toDateString(),
                ],
                'active_module_slugs'  => $activeSlugs,
                'kpis'                 => $this->buildKpis($now, $monthStart, $prevStart, $prevEnd),
                'charts'               => $this->buildCharts($now),
                'recent'               => $this->buildRecent(),
            ];
        });
    }

    // -----------------------------------------------------------------------

    private function buildKpis(Carbon $now, Carbon $monthStart, Carbon $prevStart, Carbon $prevEnd): array
    {
        $kpis = [];

        // HRM — employees
        $kpis['employees'] = $this->safely(fn () => [
            'total'    => Employee::count(),
            'active'   => Employee::where('status', 'active')->count(),
            'on_leave' => Leave::where('status', 'approved')
                ->whereDate('start_date', '<=', $now->toDateString())
                ->whereDate('end_date', '>=', $now->toDateString())
                ->distinct('employee_id')
                ->count('employee_id'),
            'new_mtd'  => Employee::whereDate('hired_at', '>=', $monthStart)->count(),
        ]);

        // HRM — leave requests
        $kpis['leave'] = $this->safely(fn () => [
            'pending'       => Leave::where('status', 'pending')->count(),
            'approved_mtd'  => Leave::where('status', 'approved')
                ->whereDate('updated_at', '>=', $monthStart)->count(),
            'rejected_mtd'  => Leave::where('status', 'rejected')
                ->whereDate('updated_at', '>=', $monthStart)->count(),
        ]);

        // HRM — attendance today
        $kpis['attendance'] = $this->safely(fn () => [
            'present_today' => AttendanceLog::whereDate('date', $now->toDateString())
                ->whereIn('status', [
                    AttendanceLog::STATUS_PRESENT,
                    AttendanceLog::STATUS_LATE,
                    AttendanceLog::STATUS_EARLY_OUT,
                    AttendanceLog::STATUS_HALF_DAY,
                ])
                ->count(),
        ]);

        // Sales
        $revMtd  = $this->safely(fn () => (float) Invoice::where('status', 'paid')
            ->whereDate('created_at', '>=', $monthStart)->sum('total_amount'), 0.0);
        $revPrev = $this->safely(fn () => (float) Invoice::where('status', 'paid')
            ->whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_amount'), 0.0);

        $kpis['sales'] = $this->safely(fn () => [
            'revenue_mtd'       => $revMtd,
            'revenue_prev_mtd'  => $revPrev,
            'revenue_change_pct' => $revPrev > 0
                ? round(($revMtd - $revPrev) / $revPrev * 100, 1)
                : ($revMtd > 0 ? 100.0 : 0.0),
            'orders_mtd'        => Order::whereDate('ordered_at', '>=', $monthStart)->count(),
            'active_customers'  => Customer::where('status', 'active')->count(),
            'open_leads'        => Lead::whereIn('status', ['new', 'qualified'])->count(),
        ]);

        // Inventory
        $kpis['inventory'] = $this->safely(fn () => [
            'total_products' => Product::count(),
            'low_stock'      => Product::whereRaw(
                "minimum_stock_level > (
                    SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0)
                    FROM stock_movements
                    WHERE stock_movements.product_id = products.id
                )"
            )->count(),
        ]);

        // Projects
        $kpis['projects'] = $this->safely(fn () => [
            'active'             => Project::whereIn('status', ['planning', 'active', 'on_hold'])->count(),
            'open_tasks'         => Task::where('status', '!=', 'done')->count(),
            'completed_tasks_mtd' => Task::where('status', 'done')
                ->whereDate('updated_at', '>=', $monthStart)->count(),
        ]);

        // Finance
        $kpis['finance'] = $this->safely(fn () => [
            'total_accounts'   => Account::count(),
            'unposted_journals' => JournalEntry::where('status', 'draft')->count(),
        ]);

        return $kpis;
    }

    private function buildCharts(Carbon $now): array
    {
        // Revenue trend — last 7 days
        $revenueTrend = collect(range(6, 0))->map(fn ($d) => [
            'label'  => $now->copy()->subDays($d)->format('M j'),
            'amount' => $this->safely(fn () => (float) Invoice::where('status', 'paid')
                ->whereDate('created_at', $now->copy()->subDays($d)->toDateString())
                ->sum('total_amount'), 0.0),
        ])->values()->all();

        // Headcount by department (active employees only)
        $headcount = $this->safely(fn () => Employee::where('status', 'active')
            ->whereNotNull('department_id')
            ->select('department_id', DB::raw('count(*) as count'))
            ->with('department:id,name')
            ->groupBy('department_id')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->department?->name ?? 'Unassigned',
                'count' => (int) $row->count,
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->all(), []);

        return [
            'revenue_trend'      => $revenueTrend,
            'headcount_by_dept'  => $headcount,
        ];
    }

    private function buildRecent(): array
    {
        $orders = $this->safely(fn () => Order::with('customer:id,name')
            ->latest('ordered_at')
            ->limit(5)
            ->get()
            ->map(fn ($o) => [
                'id'            => $o->id,
                'number'        => $o->order_number,
                'customer_name' => $o->customer?->name ?? '—',
                'total'         => (float) $o->total_amount,
                'status'        => $o->status,
                'date'          => optional($o->ordered_at)->toDateString(),
            ])
            ->all(), []);

        $leaves = $this->safely(fn () => Leave::with(['employee:id,first_name,last_name', 'leaveType:id,name'])
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'id'            => $l->id,
                'employee_name' => trim(($l->employee?->first_name ?? '') . ' ' . ($l->employee?->last_name ?? '')),
                'type'          => $l->leaveType?->name ?? '—',
                'days'          => (int) $l->days,
                'status'        => $l->status,
                'start_date'    => optional($l->start_date)->toDateString(),
            ])
            ->all(), []);

        return compact('orders', 'leaves');
    }

    /**
     * Run $fn and return its result, or $default if any exception occurs.
     * Keeps the dashboard resilient when a module's tables have no data yet.
     */
    private function safely(callable $fn, mixed $default = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
