<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CashAdvanceSettlement extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_POSTED, self::STATUS_CANCELLED];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'settlement_number',
        'cash_advance_id', 'bank_account_id',
        'settled_on', 'actual_amount', 'unused_returned',
        'payment_method', 'reference_number',
        'status',
        'journal_entry_id', 'reversal_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'settled_on'      => 'date',
        'actual_amount'   => 'decimal:2',
        'unused_returned' => 'decimal:2',
    ];

    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashAdvanceSettlementLine::class, 'settlement_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    /** Amount this settlement clears off the advance receivable. */
    public function appliedToAdvance(): float
    {
        return round((float) $this->actual_amount - (float) $this->unused_returned, 2);
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_POSTED
            && $this->reversal_journal_entry_id === null;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) $model->status = self::STATUS_POSTED;
        });
    }
}
