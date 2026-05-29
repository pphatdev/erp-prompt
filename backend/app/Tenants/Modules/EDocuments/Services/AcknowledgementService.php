<?php

namespace App\Tenants\Modules\EDocuments\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentAcknowledgement;
use App\Models\Tenant\User;

class AcknowledgementService
{
    public function acknowledge(Document $document, User $user): DocumentAcknowledgement
    {
        return DocumentAcknowledgement::firstOrCreate(
            [
                'document_id' => $document->id,
                'user_id' => $user->id,
            ],
            [
                'acknowledged_at' => now(),
            ],
        );
    }

    /**
     * @return array{totalEligible:int,acknowledged:int,pending:array<int,array{id:string,name:string,email:string}>}
     */
    public function summary(Document $document): array
    {
        $eligible = User::query()->where('is_active', true)->get(['id', 'name', 'email']);
        $acknowledgedIds = $document->acknowledgements()->pluck('user_id')->all();

        $pending = $eligible
            ->reject(fn (User $u) => in_array($u->id, $acknowledgedIds, true))
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values()
            ->all();

        return [
            'totalEligible' => $eligible->count(),
            'acknowledged' => count($acknowledgedIds),
            'pending' => $pending,
        ];
    }

    public function hasAcknowledged(Document $document, User $user): bool
    {
        return $document->acknowledgements()->where('user_id', $user->id)->exists();
    }
}
