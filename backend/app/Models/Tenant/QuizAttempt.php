<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuizAttempt extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'quiz_id',
        'application_id',
        'candidate_email',
        'candidate_name',
        'secure_token_hash',
        'invited_at',
        'expires_at',
        'started_at',
        'submitted_at',
        'status',
        'answers',
        'score',
        'passed',
        'tenant_id',
    ];

    protected $casts = [
        'invited_at'   => 'datetime',
        'expires_at'   => 'datetime',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'answers'      => 'array',
        'score'        => 'decimal:2',
        'passed'       => 'boolean',
    ];

    protected $hidden = [
        // Never leak the token hash through Resource transforms by accident.
        'secure_token_hash',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
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
