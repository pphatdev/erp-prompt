<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssetDisposal extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'asset_id',
        'disposal_date',
        'disposal_type',
        'sale_price',
        'final_nbv',
        'gain_loss',
        'gain_loss_type',
        'journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'sale_price'    => 'decimal:2',
        'final_nbv'     => 'decimal:2',
        'gain_loss'     => 'decimal:2',
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
