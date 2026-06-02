<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EcomAddress extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_addresses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'label',
        'recipient_name',
        'phone',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default_shipping',
        'is_default_billing',
        'tenant_id',
    ];

    protected $casts = [
        'is_default_shipping' => 'boolean',
        'is_default_billing' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcomCustomer::class, 'customer_id');
    }

    public function toSnapshot(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];
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
