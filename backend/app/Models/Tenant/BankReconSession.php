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

class BankReconSession extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSED];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'session_number',
        'bank_account_id',
        'start_date', 'end_date',
        'opening_balance', 'statement_ending_balance', 'book_ending_balance',
        'status',
        'closed_at', 'closed_by',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'start_date'               => 'date',
        'end_date'                 => 'date',
        'opening_balance'          => 'decimal:2',
        'statement_ending_balance' => 'decimal:2',
        'book_ending_balance'      => 'decimal:2',
        'closed_at'                => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankReconStatementLine::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** Sum of (signed) statement line amounts on this session. */
    public function statementLinesTotal(): float
    {
        return round((float) $this->statementLines()->sum('amount'), 2);
    }

    /** Count of unmatched statement lines on this session. */
    public function unmatchedLinesCount(): int
    {
        return $this->statementLines()->whereNull('matched_ledger_entry_id')->count();
    }

    /**
     * Balance check: opening + sum(line amounts) == statement_ending_balance
     * (within 0.001 tolerance). Used both to gate close() and to show a live
     * indicator in the drill-in UI.
     */
    public function balanceMatches(): bool
    {
        $expected = round((float) $this->opening_balance + $this->statementLinesTotal(), 2);
        return abs($expected - round((float) $this->statement_ending_balance, 2)) < 0.001;
    }

    /** Ready to be closed iff all lines matched AND balance check passes. */
    public function isClosable(): bool
    {
        return $this->isOpen()
            && $this->unmatchedLinesCount() === 0
            && $this->balanceMatches();
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) $model->status = self::STATUS_OPEN;
        });
    }
}
