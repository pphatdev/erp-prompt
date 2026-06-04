<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Application;
use App\Models\Tenant\Offer;
use App\Tenants\Modules\HRM\Requests\StoreOfferRequest;
use App\Tenants\Modules\HRM\Requests\UpdateOfferRequest;
use App\Tenants\Modules\HRM\Resources\OfferResource;
use App\Tenants\Modules\HRM\Services\ESignatureService;
use App\Tenants\Modules\HRM\Services\OfferService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly OfferService $offers,
        private readonly ESignatureService $esign,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Offer::class);

        $query = Offer::query()->with(['application', 'employee']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($applicationId = $request->query('applicationId')) {
            $query->where('application_id', $applicationId);
        }
        if ($employeeId = $request->query('employeeId')) {
            $query->where('employee_id', $employeeId);
        }

        $paginator = $this->paginateQuery($query->orderByDesc('created_at'), $request);

        return $this->paginatedResponse(OfferResource::class, $paginator, $request);
    }

    public function store(StoreOfferRequest $request): OfferResource
    {
        $this->authorize('create', Offer::class);

        $application = Application::findOrFail($request->input('applicationId'));

        $offer = $this->offers->createOffer($application, $request->toModelPayload());

        return new OfferResource($offer->load(['application', 'employee']));
    }

    public function show(Offer $offer): OfferResource
    {
        $this->authorize('view', $offer);

        return new OfferResource($offer->load(['application', 'employee', 'onboardingChecklist']));
    }

    public function update(UpdateOfferRequest $request, Offer $offer): OfferResource|JsonResponse
    {
        $this->authorize('update', $offer);

        if ($offer->isTerminal() || $offer->status === Offer::STATUS_SENT) {
            return response()->json([
                'message' => 'Cannot edit an offer once it has been sent. Withdraw and create a new draft instead.',
            ], 422);
        }

        $offer->update($request->toModelPayload());

        return new OfferResource($offer->fresh()->load(['application', 'employee']));
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $this->authorize('delete', $offer);

        if ($offer->status !== Offer::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft offers can be deleted. Sent / accepted offers must be voided through the e-signature provider.',
            ], 422);
        }

        $offer->delete();

        return response()->json(['message' => 'Offer deleted.'], 200);
    }

    /**
     * Move an Offer from `draft` to `sent` via the eSignature gateway.
     * Optional `provider` body field selects the provider; defaults to
     * the configured mock so demos work without DocuSign creds.
     */
    public function send(Request $request, Offer $offer): OfferResource|JsonResponse
    {
        $this->authorize('send', $offer);

        $validated = $request->validate([
            'provider' => 'nullable|string|in:mock,docusign',
        ]);

        try {
            $offer = $this->offers->sendOffer($offer, $validated);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OfferResource($offer->load(['application', 'employee']));
    }

    /**
     * Manual accept — used when an admin confirms a signature off-band
     * (e.g. wet-ink scan). Same downstream effect as the webhook path:
     * convert to Employee + seed onboarding.
     */
    public function accept(Offer $offer): OfferResource|JsonResponse
    {
        $this->authorize('send', $offer); // same gate as send

        try {
            $offer = $this->offers->markAccepted($offer);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OfferResource($offer->load(['application', 'employee', 'onboardingChecklist']));
    }

    public function decline(Request $request, Offer $offer): OfferResource|JsonResponse
    {
        $this->authorize('send', $offer);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $offer = $this->offers->markDeclined($offer, $validated['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OfferResource($offer->load(['application', 'employee']));
    }

    /**
     * eSignature provider webhook. Lives OUTSIDE auth:api so providers
     * can post directly. Tenant is still resolved by X-Tenant-Handle
     * (the provider's webhook config carries it).
     *
     * Verification:
     *   - `X-Signature` header is checked against the configured provider
     *     secret. Mock provider treats `mock-signature` as valid.
     *   - Unknown envelope id -> 202 (the provider considers this a
     *     successful delivery so it stops retrying; we treat it as "not
     *     ours, ignore").
     */
    public function webhook(Request $request): JsonResponse
    {
        $provider = (string) ($request->input('provider') ?? ESignatureService::PROVIDER_MOCK);
        $signature = (string) $request->header('X-Signature', '');

        if (!$this->esign->verifySignature($request->getContent() ?: '', $signature, $provider)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $offer = $this->esign->handleWebhookPayload($request->all(), $this->offers);

        if (!$offer) {
            return response()->json(['message' => 'Envelope ignored.'], 202);
        }

        return response()->json([
            'data' => (new OfferResource($offer))->toArray($request),
        ]);
    }
}
