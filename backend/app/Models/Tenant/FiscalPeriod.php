<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FiscalPeriod extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_OPEN   = 'open';
    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_LOCKED];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'period_number', 'name',
        'start_date', 'end_date',
        'status',
        'locked_at', 'locked_by',
        'retained_earnings_account_id',
        'closing_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'locked_at'  => 'datetime',
    ];

    public function retainedEarningsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'retained_earnings_account_id');
    }

    public function closingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isClosable(): bool
    {
        return $this->isOpen();
    }

    public function isReopenable(): bool
    {
        return $this->isLocked();
    }

    /** True if the given Y-m-d date falls within this period (inclusive). */
    public function contains(string $date): bool
    {
        $start = optional($this->start_date)->toDateString();
        $end   = optional($this->end_date)->toDateString();
        return $start !== null && $end !== null && $date >= $start && $date <= $end;
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
