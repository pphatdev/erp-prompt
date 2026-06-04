<?php

namespace App\Models\Tenant;

use App\Models\Casts\EncryptedWithFallback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
        'manager_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'gender',
        'phone',
        'image_path',
        'hired_at',
        'base_salary',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'status',
        'employment_type',
        'tenant_id',
    ];

    protected $casts = [
        'hired_at'            => 'date',
        // EncryptedWithFallback degrades gracefully if the column already
        // holds plaintext (legacy/seeded rows) — see the cast for details.
        'base_salary'         => EncryptedWithFallback::class,
        'bank_name'           => EncryptedWithFallback::class,
        'bank_account_name'   => EncryptedWithFallback::class,
        'bank_account_number' => EncryptedWithFallback::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'custodian_employee_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
