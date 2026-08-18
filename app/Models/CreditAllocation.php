<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which payment settled which charge, and by how much.
 *
 * Without these rows a balance is just a number and every debt looks the same
 * age — "₱200, 45 days na" is only answerable because the payment recorded
 * what it paid off.
 */
class CreditAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_transaction_id',
        'charge_transaction_id',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'payment_transaction_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'charge_transaction_id');
    }
}
