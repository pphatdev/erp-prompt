<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DebitNote extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_ISSUED    = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ISSUED, self::STATUS_CANCELLED];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'debit_note_number',
        'customer_id', 'invoice_id',
        'revenue_account_id', 'ar_account_id',
        'issue_date', 'amount', 'currency',
        'reason',
        'status',
        'journal_entry_id', 'reversal_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'amount'     => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ar_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_ISSUED
            && $this->reversal_journal_entry_id === null;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status))    $model->status   = self::STATUS_ISSUED;
            if (!empty($model->currency)) $model->currency = strtoupper($model->currency);
        });
    }
}
