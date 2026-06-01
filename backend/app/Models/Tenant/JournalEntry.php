<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'reference_number',
        'description',
        'entry_date',
        'status',
        'reverses_journal_id',
        'reversed_by_journal_id',
        'tenant_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_journal_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_journal_id');
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
