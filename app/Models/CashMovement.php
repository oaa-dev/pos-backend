<?php

namespace App\Models;

use App\Enums\CashMovementTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_drawer_session_id',
        'type',
        'amount',
        'reason',
        'reference_type',
        'reference_id',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementTypeEnum::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class, 'cash_drawer_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** Signed for arithmetic; the column itself stays positive. */
    public function signedAmount(): string
    {
        return $this->type->isInflow()
            ? (string) $this->amount
            : '-'.$this->amount;
    }
}
