<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Holiday;
use App\Tenants\Modules\HRM\Resources\HolidayResource;
use App\Tenants\Modules\HRM\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    use Paginates;

    public function __construct(private readonly HolidayService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Holiday::class);

        $query = $this->service->buildQuery();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where('name', 'ilike', $like);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }
        if ($request->boolean('recurring_only')) {
            $query->where('is_recurring', true);
        }

        return $this->paginatedResponse(HolidayResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): HolidayResource
    {
        $this->authorize('create', Holiday::class);
        $data = $this->validatePayload($request);
        $holiday = $this->service->create($data);
        return new HolidayResource($holiday);
    }

    public function show(Holiday $holiday): HolidayResource
    {
        $this->authorize('view', $holiday);
        return new HolidayResource($holiday);
    }

    public function update(Request $request, Holiday $holiday): HolidayResource
    {
        $this->authorize('update', $holiday);
        $data = $this->validatePayload($request, false);
        $holiday = $this->service->update($holiday, $data);
        return new HolidayResource($holiday);
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $this->authorize('delete', $holiday);
        $this->service->delete($holiday);
        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Calendar feed: expanded holiday occurrences + leaves in [from, to].
     */
    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Holiday::class);
        $data = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);
        return response()->json(['data' => $this->service->calendarFeed($data['from'], $data['to'])]);
    }

    /**
     * Personal calendar feed for the current authenticated user.
     * Returns holidays in range (everyone sees the same) + only this user's
     * own leaves (any status). No HRM perms required - any authenticated user
     * can see their own schedule.
     */
    public function myCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $user = $request->user();
        $employeeId = $user?->employee?->id;

        return response()->json([
            'data' => $this->service->personalCalendarFeed($data['from'], $data['to'], $employeeId),
        ]);
    }

    private function validatePayload(Request $request, bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'name'         => "$prefix|string|max:200",
            'date'         => "$prefix|date",
            'type'         => 'sometimes|string|in:public,company,optional',
            'is_recurring' => 'sometimes|boolean',
            'notes'        => 'sometimes|nullable|string|max:2000',
        ]);
    }
}
