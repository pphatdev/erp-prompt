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

class PosOrder extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'pos_orders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_number',
        'shift_id',
        'terminal_id',
        'cashier_id',
        'client_uuid',
        'customer_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'currency',
        'status',
        'journal_entry_id',
        'void_journal_entry_id',
        'placed_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'placed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public const STATUS_PAID     = 'paid';
    public const STATUS_VOIDED   = 'voided';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUSES = [self::STATUS_PAID, self::STATUS_VOIDED, self::STATUS_REFUNDED];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function voidJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'void_journal_entry_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isRefundable(): bool
    {
        return $this->status === self::STATUS_PAID;
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
