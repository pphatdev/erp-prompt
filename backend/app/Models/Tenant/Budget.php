<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Budget extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'budget_number', 'name',
        'start_date', 'end_date',
        'status',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /** Drafts are the only mutable state. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isActivatable(): bool
    {
        return $this->isDraft() && $this->lines()->exists();
    }

    public function isArchivable(): bool
    {
        return $this->isActive();
    }

    public function expectedTotal(): float
    {
        return round((float) $this->lines()->sum('expected_amount'), 2);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) $model->status = self::STATUS_DRAFT;
        });
    }
}
