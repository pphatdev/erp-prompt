<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Interview extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'application_id',
        'quiz_attempt_id',
        'title',
        'round',
        'scheduled_at',
        'duration_minutes',
        'mode',
        'location',
        'status',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }

    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'interview_interviewer',
            'interview_id',
            'interviewer_id'
        )->withTimestamps();
    }

    /**
     * Average of submitted feedback ratings, or null when no ratings yet.
     */
    public function averageRating(): ?float
    {
        $ratings = $this->feedback()->whereNotNull('rating')->pluck('rating');
        if ($ratings->isEmpty()) {
            return null;
        }
        return round((float) $ratings->avg(), 2);
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
