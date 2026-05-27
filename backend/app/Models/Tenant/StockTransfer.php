<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockTransfer extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED   = 'received';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'initiated_by',
        'initiated_at',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected $casts = [
        'initiated_at'  => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at'   => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isDraft(): bool      { return $this->status === self::STATUS_DRAFT; }
    public function isInTransit(): bool  { return $this->status === self::STATUS_IN_TRANSIT; }
    public function isReceived(): bool   { return $this->status === self::STATUS_RECEIVED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
