<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssetRevaluationLog extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'asset_id',
        'appraisal_date',
        'previous_value',
        'appraisal_value',
        'adjustment_amount',
        'adjustment_type',
        'appraiser',
        'notes',
        'journal_entry_id',
        'tenant_id',
    ];

    protected $casts = [
        'appraisal_date'    => 'date',
        'previous_value'    => 'decimal:2',
        'appraisal_value'   => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
