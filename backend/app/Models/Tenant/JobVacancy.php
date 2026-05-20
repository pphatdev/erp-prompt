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

class JobVacancy extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'job_vacancies';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'department_id',
        'position_id',
        'title',
        'description',
        'location',
        'employment_type',
        'experience_min_years',
        'experience_max_years',
        'salary_min',
        'salary_max',
        'vacancies_count',
        'status',
        'posted_at',
        'closes_at',
        'tenant_id',
    ];

    protected $casts = [
        'posted_at' => 'date',
        'closes_at' => 'date',
        'salary_min' => 'decimal:4',
        'salary_max' => 'decimal:4',
        'vacancies_count' => 'integer',
        'experience_min_years' => 'integer',
        'experience_max_years' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
