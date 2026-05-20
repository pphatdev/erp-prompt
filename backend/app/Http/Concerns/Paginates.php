<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Standard list-endpoint pagination for tenant API controllers.
 *
 * Query API:
 *   ?page=N&limit=N    — page is 1-based, limit clamped to [1, max]
 *
 * Response envelope:
 *   {
 *     "data":       [ ... ],
 *     "pagination": { page, limit, total, totalPages }
 *   }
 */
trait Paginates
{
    protected int $paginatesDefault = 15;

    protected int $paginatesMax = 100;

    protected function resolveLimit(Request $request): int
    {
        $raw = (int) $request->query('limit', (string) $this->paginatesDefault);

        return max(1, min($raw, $this->paginatesMax));
    }

    /** @param Builder|Relation $query */
    protected function paginateQuery($query, Request $request): LengthAwarePaginator
    {
        return $query->paginate($this->resolveLimit($request))->withQueryString();
    }

    /**
     * @param  class-string  $resourceClass  JsonResource subclass
     */
    protected function paginatedResponse(
        string $resourceClass,
        LengthAwarePaginator $paginator,
        Request $request
    ): JsonResponse {
        $data = $paginator->getCollection()
            ->map(fn ($item) => (new $resourceClass($item))->toArray($request))
            ->all();

        return response()->json([
            'data'       => $data,
            'pagination' => [
                'page'       => $paginator->currentPage(),
                'limit'      => $paginator->perPage(),
                'total'      => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }
}
