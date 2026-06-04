<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Offer;
use DomainException;
use Illuminate\Support\Str;

/**
 * Provider-agnostic eSignature gateway.
 *
 * Today only the `mock` provider is wired (it stamps a deterministic
 * envelope id + immediately treats the next webhook as a signed event).
 * The DocuSign / Adobe Sign branches stay shape-compatible — when those
 * credentials are configured per-tenant via `hrm.recruitment.esign.*`
 * settings, the same call sites work without changing.
 *
 * Webhook ingress lives in {@see handleWebhookPayload()}: the provider
 * posts a signature event, this service translates it into an Offer state
 * change by delegating to {@see OfferService::markAccepted/markDeclined}.
 *
 * Why a service rather than a Laravel facade: per-tenant credentials, and
 * the orchestrating OfferService needs to inject the gateway via the
 * container so a future DocuSignESignatureService can swap in cleanly.
 */
class ESignatureService
{
    public const PROVIDER_MOCK     = 'mock';
    public const PROVIDER_DOCUSIGN = 'docusign';

    /**
     * Stamp an envelope id for the offer. With the mock provider this is
     * deterministic and side-effect-free; with DocuSign it would POST the
     * envelope definition to /v2.1/accounts/{accountId}/envelopes and
     * return the real envelope id.
     *
     * @return array{provider: string, envelopeId: string}
     */
    public function createEnvelope(Offer $offer, array $options = []): array
    {
        $provider = $options['provider'] ?? self::PROVIDER_MOCK;

        return match ($provider) {
            self::PROVIDER_MOCK     => $this->mockEnvelope($offer),
            self::PROVIDER_DOCUSIGN => $this->docusignEnvelope($offer, $options),
            default => throw new DomainException("Unknown eSignature provider: {$provider}"),
        };
    }

    /**
     * Translate an inbound webhook into Offer state. Returns the resolved
     * Offer when the payload was understood, null when the envelope id is
     * unknown to this tenant (so the controller can return 202 / 404).
     *
     * The OfferService is injected by the caller (controller) so this
     * service stays free of circular dependencies — OfferService injects
     * ESignatureService at construction.
     */
    public function handleWebhookPayload(array $payload, OfferService $offers): ?Offer
    {
        $envelopeId = $this->extractEnvelopeId($payload);
        if ($envelopeId === null) {
            return null;
        }

        $offer = Offer::query()->where('esign_envelope_id', $envelopeId)->first();
        if (!$offer) {
            return null;
        }

        $event = $this->extractEvent($payload);

        return match ($event) {
            'completed', 'signed', 'accepted' => $offers->markAccepted($offer, $payload),
            'declined', 'voided'              => $offers->markDeclined($offer, $payload['reason'] ?? null, $payload),
            default                           => $offer,
        };
    }

    /**
     * Validate that a webhook signature matches the configured tenant
     * secret. The mock provider treats `mock-signature` as valid; DocuSign
     * uses HMAC-SHA256 over the raw body. Override in tests by binding a
     * subclass.
     */
    public function verifySignature(string $rawBody, string $signature, string $provider): bool
    {
        if ($provider === self::PROVIDER_MOCK) {
            return $signature === 'mock-signature';
        }

        // DocuSign Connect HMAC verification — real implementation would
        // resolve the per-tenant HMAC key from settings and hash the raw
        // body. Until DocuSign credentials are configured, this path is
        // unreachable from the routes.
        return false;
    }

    private function mockEnvelope(Offer $offer): array
    {
        return [
            'provider'   => self::PROVIDER_MOCK,
            'envelopeId' => 'mock-' . Str::uuid()->toString(),
        ];
    }

    private function docusignEnvelope(Offer $offer, array $options): array
    {
        // Placeholder — real implementation issues a POST to DocuSign's
        // envelope API and returns the response envelope id. Throwing here
        // keeps the call site explicit until credentials land.
        throw new DomainException(
            'DocuSign provider not yet configured. Set hrm.recruitment.esign.docusign_account_id / .integrator_key / .private_key first.'
        );
    }

    private function extractEnvelopeId(array $payload): ?string
    {
        return $payload['envelopeId']
            ?? $payload['envelope_id']
            ?? ($payload['data']['envelopeId'] ?? null);
    }

    private function extractEvent(array $payload): string
    {
        $event = $payload['event']
            ?? $payload['status']
            ?? ($payload['data']['envelopeStatus'] ?? null);

        return is_string($event) ? strtolower($event) : 'unknown';
    }
}
