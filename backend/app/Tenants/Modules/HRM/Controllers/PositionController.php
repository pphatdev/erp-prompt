<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Position;
use App\Tenants\Modules\HRM\Requests\StorePositionRequest;
use App\Tenants\Modules\HRM\Resources\PositionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        $paginator = $this->paginateQuery(Position::query()->orderBy('title'), $request);

        return $this->paginatedResponse(PositionResource::class, $paginator, $request);
    }

    public function store(StorePositionRequest $request): PositionResource
    {
        $this->authorize('create', Position::class);

        return new PositionResource(Position::create($request->validated()));
    }

    public function show(Position $position): PositionResource
    {
        $this->authorize('view', $position);

        return new PositionResource($position);
    }

    public function update(StorePositionRequest $request, Position $position): PositionResource
    {
        $this->authorize('update', $position);

        $position->update($request->validated());

        return new PositionResource($position);
    }

    public function destroy(Position $position): JsonResponse
    {
        $this->authorize('delete', $position);

        $position->delete();

        return response()->json(['message' => 'Position removed.'], 200);
    }
}
