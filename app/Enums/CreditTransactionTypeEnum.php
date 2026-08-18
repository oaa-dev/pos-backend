<?php

namespace App\Enums;

enum CreditTransactionTypeEnum: string
{
    /** A sale charged to the customer's account. Increases what they owe. */
    case CHARGE = 'charge';

    /** Money handed over against the balance. Decreases what they owe. */
    case PAYMENT = 'payment';

    /**
     * A correction. Signed either way, because the ledger is append-only —
     * a mistaken charge is reversed by an adjustment, never deleted.
     */
    case ADJUSTMENT = 'adjustment';

    /** Debt forgiven by the owner. Decreases the balance, is not a payment. */
    case WRITEOFF = 'writeoff';

    /** Whether this type adds to what the customer owes. */
    public function increasesBalance(): bool
    {
        return $this === self::CHARGE;
    }

    /**
     * Whether this settles outstanding charges and so allocates against them.
     * An adjustment does not: it corrects the ledger rather than paying it.
     */
    public function settlesCharges(): bool
    {
        return in_array($this, [self::PAYMENT, self::WRITEOFF], true);
    }

    /** Whether this represents cash arriving in the drawer. */
    public function isCashCollection(): bool
    {
        return $this === self::PAYMENT;
    }
}
