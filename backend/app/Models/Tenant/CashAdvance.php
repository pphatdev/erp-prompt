<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CashAdvance extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_OPEN              = 'open';
    public const STATUS_PARTIALLY_SETTLED = 'partially_settled';
    public const STATUS_CLOSED            = 'closed';
    public const STATUS_CANCELLED         = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PARTIALLY_SETTLED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses where the advance still owes settlement / can be acted on. */
    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PARTIALLY_SETTLED,
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'advance_number',
        'employee_id', 'bank_account_id', 'receivable_account_id',
        'issued_on', 'amount', 'settled_amount', 'currency',
        'payment_method', 'reference_number', 'purpose',
        'status',
        'journal_entry_id', 'reversal_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'issued_on'      => 'date',
        'amount'         => 'decimal:2',
        'settled_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    /** Amount still on the books as a receivable from this employee. */
    public function outstandingAmount(): float
    {
        return round((float) $this->amount - (float) $this->settled_amount, 2);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && (float) $this->settled_amount <= 0.001
            && $this->reversal_journal_entry_id === null;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status))    $model->status   = self::STATUS_OPEN;
            if (!empty($model->currency)) $model->currency = strtoupper($model->currency);
        });
    }
}
