<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Per-tenant configurable status (Workflow Status).
 *
 * Modules use a dotted key ("hrm.application", "hrm.leave"…) and each module
 * owns a fixed set of `key` values with their allowed transitions, label,
 * color, icon, and ordering. The hardcoded STATUS_FLOW constants on domain
 * models are seed data only — at runtime the DB is authoritative.
 */
class WorkflowStatus extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'module',
        'key',
        'label',
        'color',
        'icon',
        'sequence',
        'is_initial',
        'is_terminal',
        'allowed_next',
        'metadata',
        'tenant_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'is_initial' => 'boolean',
        'is_terminal' => 'boolean',
        'allowed_next' => 'array',
        'metadata' => 'array',
    ];

    public const COLORS = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module)->orderBy('sequence');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
