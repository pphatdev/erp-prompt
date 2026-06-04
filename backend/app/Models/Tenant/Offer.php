<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Casts\EncryptedWithFallback;
use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Binding job-offer document issued to a hired Application.
 *
 * Compensation columns (`base_salary`, `signing_bonus`) use
 * EncryptedWithFallback so leaked DB dumps cannot read figures and legacy
 * plaintext rows still decrypt cleanly. eSignature provider state is
 * stored on the row itself (`esign_provider`, `esign_envelope_id`,
 * `esign_payload`) so the workflow stays auditable without spawning a
 * dedicated provider-state table.
 *
 * Lifecycle (per `hrm.offer` workflow_statuses module):
 *   draft -> sent -> accepted | declined | expired.
 */
class Offer extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'application_id',
        'employee_id',
        'reference_number',
        'title',
        'effective_date',
        'expires_at',
        'base_salary',
        'signing_bonus',
        'currency',
        'probation_months',
        'status',
        'esign_provider',
        'esign_envelope_id',
        'esign_payload',
        'sent_at',
        'signed_at',
        'declined_at',
        'decline_reason',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'effective_date'   => 'date',
        'expires_at'       => 'date',
        'sent_at'          => 'datetime',
        'signed_at'        => 'datetime',
        'declined_at'      => 'datetime',
        'esign_payload'    => 'array',
        'probation_months' => 'integer',
        'base_salary'      => EncryptedWithFallback::class,
        'signing_bonus'    => EncryptedWithFallback::class,
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function onboardingChecklist(): HasOne
    {
        return $this->hasOne(OnboardingChecklist::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED], true);
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
