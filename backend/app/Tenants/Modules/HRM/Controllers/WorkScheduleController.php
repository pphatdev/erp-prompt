<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WorkSchedule;
use App\Tenants\Modules\HRM\Resources\WorkScheduleResource;
use App\Tenants\Modules\HRM\Services\WorkScheduleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints
 *   GET    /work-schedules/snapshot?targetType=global|department|employee&targetId=...
 *     Returns 7 rows (one per ISO weekday). Missing days are synthesised
 *     as off-placeholders so the editor can render a full week.
 *
 *   GET    /work-schedules?targetType=...&targetId=...
 *     Returns only the rows that actually exist (admin / debugging).
 *
 *   PUT    /work-schedules
 *     Bulk upsert one week for a target. Body:
 *       { targetType, targetId, days: [ { dayOfWeek, isWorkDay, intervals: [{start,end}, ...] }, ... ] }
 *
 *   DELETE /work-schedules?targetType=...&targetId=...
 *     Clears all override rows for the target. Global is rejected.
 */
class WorkScheduleController extends Controller
{
    public function __construct(private readonly WorkScheduleService $workSchedules)
    {
    }

    public function snapshot(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        $data = $request->validate([
            'targetType' => 'required|string|in:global,department,employee',
            'targetId'   => 'nullable|uuid',
        ]);

        $rows = $this->workSchedules->snapshotFor(
            $data['targetType'],
            $data['targetType'] === WorkSchedule::TARGET_GLOBAL ? null : ($data['targetId'] ?? null),
        );

        return response()->json([
            'data' => $rows->all(),
            'meta' => [
                'targetType' => $data['targetType'],
                'targetId'   => $data['targetType'] === WorkSchedule::TARGET_GLOBAL ? null : ($data['targetId'] ?? null),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        $query = WorkSchedule::query();
        if ($type = $request->query('targetType')) {
            $query->where('target_type', $type);
        }
        if ($request->has('targetId')) {
            $targetId = $request->query('targetId');
            $query->where('target_id', $targetId === '' ? null : $targetId);
        }

        $rows = $query->orderBy('target_type')
            ->orderBy('target_id')
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'data' => WorkScheduleResource::collection($rows)->toArray($request),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $this->authorize('create', WorkSchedule::class);

        $data = $request->validate([
            'targetType'             => 'required|string|in:global,department,employee',
            'targetId'               => 'nullable|uuid',
            'days'                   => 'required|array|min:1|max:7',
            'days.*.dayOfWeek'       => 'required|integer|between:1,7',
            'days.*.isWorkDay'       => 'required|boolean',
            'days.*.intervals'       => 'sometimes|array',
            'days.*.intervals.*.start' => 'required_with:days.*.intervals|string|regex:/^[0-2]\d:[0-5]\d$/',
            'days.*.intervals.*.end'   => 'required_with:days.*.intervals|string|regex:/^[0-2]\d:[0-5]\d$/',
        ]);

        try {
            $rows = $this->workSchedules->upsertWeek(
                $data['targetType'],
                $data['targetType'] === WorkSchedule::TARGET_GLOBAL ? null : ($data['targetId'] ?? null),
                $data['days'],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => WorkScheduleResource::collection($rows)->toArray($request),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorize('delete', WorkSchedule::class);

        $data = $request->validate([
            'targetType' => 'required|string|in:department,employee',
            'targetId'   => 'required|uuid',
        ]);

        try {
            $deleted = $this->workSchedules->clearTarget($data['targetType'], $data['targetId']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Cleared {$deleted} work-schedule row(s). Target now inherits from its parent layer.",
            'deleted' => $deleted,
        ]);
    }
}
