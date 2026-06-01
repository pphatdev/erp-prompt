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

class Expense extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_POSTED, self::STATUS_CANCELLED];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'expense_number',
        'bank_account_id', 'supplier_id',
        'paid_on', 'total', 'currency',
        'payment_method', 'reference_number',
        'status',
        'journal_entry_id', 'reversal_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'total'   => 'decimal:2',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class);
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
            if (empty($model->status))    $model->status   = self::STATUS_POSTED;
            if (!empty($model->currency)) $model->currency = strtoupper($model->currency);
        });
    }
}
