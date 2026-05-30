<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DepreciationLog extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'asset_id',
        'period_date',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value',
        'method',
        'journal_entry_id',
        'tenant_id',
    ];

    protected $casts = [
        'period_date'              => 'date',
        'depreciation_amount'      => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value'               => 'decimal:2',
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
