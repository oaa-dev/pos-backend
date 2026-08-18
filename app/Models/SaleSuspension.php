<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleSuspension extends Model
{
    use HasFactory;

    public const RESUME_WINDOW_MINUTES = 15;

    protected $fillable = [
        'branch_id',
        'user_id',
        'label',
        'payload',
        'suspended_at',
        'resumed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'suspended_at' => 'datetime',
            'resumed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function expiresAt(): ?CarbonInterface
    {
        return $this->suspended_at?->copy()->addMinutes(self::RESUME_WINDOW_MINUTES);
    }

    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? true;
    }
}
