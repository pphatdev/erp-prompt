<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomPayment;
use App\Tenants\Modules\Ecommerce\Services\CheckoutService;
use App\Tenants\Modules\Ecommerce\Services\RefundService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payment gateway webhooks. Signature validation is provider-specific —
 * Stripe (HMAC-SHA256 over the raw body + timestamp), ABA / Wing follow
 * similar HMAC patterns. The webhook secret per provider lives in Settings
 * under `ecommerce.payment.{provider}.webhook_secret`.
 *
 * Idempotent on the provider's event id (Stripe: `evt_*`). The first call
 * for an event id transitions the order; replays no-op.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly RefundService $refunds,
        private readonly SettingService $settings,
    ) {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        $secret = (string) $this->settings->get("ecommerce.payment.{$provider}.webhook_secret", '');
        if ($secret === '') {
            return response()->json(['error' => 'webhook_not_configured'], 503);
        }

        $rawBody = $request->getContent();
        if (!$this->verifySignature($provider, $request, $rawBody, $secret)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = $request->json()->all();
        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? null;
        if (!$eventId || !$eventType) {
            return response()->json(['error' => 'malformed_event'], 422);
        }

        try {
            return $this->dispatch($provider, $eventId, $eventType, $payload);
        } catch (Throwable $e) {
            Log::error('Ecom webhook handler failed', [
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'handler_failed', 'message' => $e->getMessage()], 500);
        }
    }

    private function dispatch(string $provider, string $eventId, string $eventType, array $payload): JsonResponse
    {
        $clientUuid = $this->extractClientUuid($payload);
        if (!$clientUuid) {
            return response()->json(['accepted' => true, 'note' => 'no_client_uuid']);
        }

        $payment = EcomPayment::where('client_uuid', $clientUuid)->first();
        if (!$payment) {
            return response()->json(['accepted' => true, 'note' => 'no_matching_payment']);
        }

        $order = $payment->order;
        if (!$order) {
            return response()->json(['accepted' => true, 'note' => 'no_matching_order']);
        }

        // Idempotency: if we've already processed this gateway charge id,
        // no-op. Stripe redelivers events freely, so this guard is essential.
        $chargeId = $payload['data']['object']['id'] ?? $payload['charge_id'] ?? null;
        if ($chargeId && $payment->provider_charge_id === $chargeId
            && $payment->status === EcomPayment::STATUS_SUCCEEDED) {
            return response()->json(['accepted' => true, 'note' => 'duplicate_event']);
        }

        if ($this->isSuccessEvent($provider, $eventType)) {
            $this->checkout->confirm($order, [
                'charge_id' => $chargeId,
                'event_id' => $eventId,
                'gateway_fee' => $this->extractGatewayFee($payload),
                'raw' => $payload,
            ]);
            return response()->json(['accepted' => true]);
        }

        if ($this->isFailureEvent($provider, $eventType)) {
            $payment->update([
                'status' => EcomPayment::STATUS_FAILED,
                'failure_code' => $payload['data']['object']['failure_code'] ?? 'gateway_failure',
                'failure_message' => $payload['data']['object']['failure_message'] ?? null,
                'raw_payload' => $payload,
                'failed_at' => now(),
            ]);
            return response()->json(['accepted' => true]);
        }

        // Unknown / not-yet-implemented event types are accepted so the
        // provider stops retrying.
        return response()->json(['accepted' => true, 'note' => 'unhandled_event_type']);
    }

    private function verifySignature(string $provider, Request $request, string $rawBody, string $secret): bool
    {
        if ($provider === 'stripe') {
            $header = $request->header('Stripe-Signature', '');
            if (!is_string($header) || $header === '') {
                return false;
            }
            $parts = [];
            foreach (explode(',', $header) as $segment) {
                [$k, $v] = array_pad(explode('=', $segment, 2), 2, '');
                $parts[$k] = $v;
            }
            $timestamp = $parts['t'] ?? '';
            $signature = $parts['v1'] ?? '';
            if (!$timestamp || !$signature) {
                return false;
            }
            $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);
            return hash_equals($expected, $signature);
        }

        // Generic HMAC-SHA256 over body for ABA / Wing / manual placeholders.
        $header = $request->header('X-Webhook-Signature', '');
        if (!is_string($header) || $header === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $header);
    }

    private function isSuccessEvent(string $provider, string $type): bool
    {
        return match (true) {
            $provider === 'stripe' && in_array($type, [
                'payment_intent.succeeded',
                'charge.succeeded',
            ], true) => true,
            $type === 'payment.success' => true,
            default => false,
        };
    }

    private function isFailureEvent(string $provider, string $type): bool
    {
        return match (true) {
            $provider === 'stripe' && in_array($type, [
                'payment_intent.payment_failed',
                'charge.failed',
            ], true) => true,
            $type === 'payment.failed' => true,
            default => false,
        };
    }

    private function extractClientUuid(array $payload): ?string
    {
        return $payload['data']['object']['metadata']['client_uuid']
            ?? $payload['metadata']['client_uuid']
            ?? $payload['client_uuid']
            ?? null;
    }

    private function extractGatewayFee(array $payload): float
    {
        $fee = $payload['data']['object']['application_fee_amount']
            ?? $payload['gateway_fee']
            ?? 0;
        // Stripe reports fees in the smallest currency unit; assume cents → dollars.
        return is_int($fee) ? $fee / 100 : (float) $fee;
    }
}
