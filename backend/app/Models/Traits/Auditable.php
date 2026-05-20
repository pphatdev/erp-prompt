<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait for tracking changes to models for audit purposes.
 * Record old/new values, actor handle, and timestamp.
 */
trait Auditable
{
    public static function bootAuditable()
    {
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = $model->getOriginal();

            self::logAudit('update', $model, [
                'old' => array_intersect_key($original, $changes),
                'new' => $changes,
            ]);
        });

        static::created(function ($model) {
            self::logAudit('create', $model, [
                'new' => $model->toArray(),
            ]);
        });

        static::deleted(function ($model) {
            self::logAudit('delete', $model, [
                'old' => $model->toArray(),
            ]);
        });
    }

    protected static function logAudit(string $action, $model, array $payload)
    {
        $actor = Auth::user();
        
        // In a real implementation, this would write to an 'audit_logs' table
        // For now, we log it to the system log for traceability.
        Log::info("Audit Log: {$action}", [
            'model' => get_class($model),
            'id' => $model->id,
            'actor_id' => $actor ? $actor->id : 'system',
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
