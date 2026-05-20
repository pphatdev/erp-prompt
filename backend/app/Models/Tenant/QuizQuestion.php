<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuizQuestion extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'quiz_id',
        'sequence',
        'prompt',
        'question_type',
        'options',
        'correct_answer',
        'points',
        'tenant_id',
    ];

    protected $casts = [
        'options'        => 'array',
        // correct_answer is stored as encrypted JSON ciphertext. Decode the
        // plaintext JSON in the service layer (QuizService::correctAnswerFor)
        // because Laravel's 'encrypted:array' cast collides with 'array' on
        // null-safe defaults.
        'correct_answer' => 'encrypted',
        'sequence'       => 'integer',
        'points'         => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
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
