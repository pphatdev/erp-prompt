<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InterviewFeedback extends Model
{
    use BelongsToTenant, Auditable;

    protected $table = 'interview_feedback';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'interview_id',
        'interviewer_id',
        'rating',
        'strengths',
        'concerns',
        'recommendation',
        'submitted_at',
        'tenant_id',
    ];

    protected $casts = [
        'rating'       => 'decimal:1',
        'submitted_at' => 'datetime',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_id');
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
