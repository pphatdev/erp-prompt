<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Subscription;
use App\Tenants\Modules\Sales\Resources\SubscriptionResource;
use App\Tenants\Modules\Sales\Services\SubscriptionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    use Paginates;

    public function __construct(private readonly SubscriptionService $subs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query()->with(['customer', 'items'])->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(SubscriptionResource::class, $paginator, $request);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load(['customer', 'items']));
    }

    public function renew(Request $request, Subscription $subscription): SubscriptionResource|JsonResponse
    {
        $data = $request->validate([
            'cycle' => ['sometimes', Rule::in([Subscription::CYCLE_MONTHLY, Subscription::CYCLE_ANNUAL])],
        ]);

        try {
            $this->subs->renew($subscription, $data['cycle'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new SubscriptionResource($subscription->fresh(['customer', 'items']));
    }

    public function changePlan(Request $request, Subscription $subscription): SubscriptionResource|JsonResponse
    {
        $data = $request->validate([
            'product_id'        => 'required|uuid|exists:products,id',
            'variant_id'        => 'sometimes|nullable|uuid|exists:product_variants,id',
            'target_product_id' => 'sometimes|nullable|uuid|exists:products,id',
            'action'            => ['required', Rule::in(['upgrade', 'downgrade'])],
        ]);

        try {
            $this->subs->changePlan($subscription, $data, $data['action']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new SubscriptionResource($subscription->fresh(['customer', 'items']));
    }

    public function cancel(Request $request, Subscription $subscription): SubscriptionResource|JsonResponse
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        try {
            $this->subs->cancel($subscription, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new SubscriptionResource($subscription->fresh(['customer', 'items']));
    }
}
