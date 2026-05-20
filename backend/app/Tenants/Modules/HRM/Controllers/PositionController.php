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
        $paginator = $this->paginateQuery(Position::query()->orderBy('title'), $request);

        return $this->paginatedResponse(PositionResource::class, $paginator, $request);
    }

    public function store(StorePositionRequest $request): PositionResource
    {
        return new PositionResource(Position::create($request->validated()));
    }

    public function show(Position $position): PositionResource
    {
        return new PositionResource($position);
    }

    public function update(StorePositionRequest $request, Position $position): PositionResource
    {
        $position->update($request->validated());

        return new PositionResource($position);
    }

    public function destroy(Position $position): JsonResponse
    {
        $position->delete();

        return response()->json(['message' => 'Position removed.'], 200);
    }
}
