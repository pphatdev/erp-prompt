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

class Bill extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID           = 'paid';
    public const STATUS_CANCELLED      = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    /** Statuses where the bill still owes money — used by archive guards / picker filters. */
    public const OPEN_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_PAID,
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bill_number', 'supplier_invoice_number',
        'supplier_id', 'po_id',
        'issue_date', 'due_date', 'currency',
        'subtotal', 'tax_amount', 'total', 'paid_amount',
        'status',
        'payable_account_id', 'journal_entry_id', 'reversal_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'due_date'    => 'date',
        'subtotal'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPostable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReversible(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID], true)
            && $this->journal_entry_id !== null
            && $this->reversal_journal_entry_id === null;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status))   $model->status = self::STATUS_DRAFT;
            if (!empty($model->currency)) $model->currency = strtoupper($model->currency);
        });
    }
}
