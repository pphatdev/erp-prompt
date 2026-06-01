<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankReconStatementLine extends Model
{
    use BelongsToTenant, Auditable;

    protected $table = 'bank_recon_statement_lines';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'session_id',
        'statement_date', 'description', 'reference_number',
        'amount',
        'matched_ledger_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'amount'         => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BankReconSession::class, 'session_id');
    }

    public function matchedLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'matched_ledger_entry_id');
    }

    public function isMatched(): bool
    {
        return $this->matched_ledger_entry_id !== null;
    }

    /** Statement perspective: positive amount = deposit / inflow. */
    public function isDeposit(): bool
    {
        return (float) $this->amount > 0.001;
    }

    public function isWithdrawal(): bool
    {
        return (float) $this->amount < -0.001;
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
