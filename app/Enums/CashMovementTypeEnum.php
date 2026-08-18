<?php

namespace App\Enums;

enum CashMovementTypeEnum: string
{
    case FLOAT_IN = 'float_in';
    case STORE_EXPENSE = 'store_expense';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case OWNER_WITHDRAWAL = 'owner_withdrawal';
    case PETTY_CASH = 'petty_cash';
    case DROP = 'drop';
    case GCASH_CASH_IN = 'gcash_cash_in';
    case GCASH_CASH_OUT = 'gcash_cash_out';
    case PAID_IN = 'paid_in';
    case PAID_OUT = 'paid_out';

    /**
     * Whether this adds cash to the drawer.
     *
     * GCash is the counter-intuitive pair: a customer cashing **in** hands
     * over pesos, so the drawer gains and the e-wallet falls. Cashing out is
     * the reverse.
     */
    public function isInflow(): bool
    {
        return in_array($this, [
            self::FLOAT_IN,
            self::GCASH_CASH_IN,
            self::PAID_IN,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::FLOAT_IN => 'Dagdag panukli',
            self::STORE_EXPENSE => 'Store expense',
            self::SUPPLIER_PAYMENT => 'Supplier payment',
            self::OWNER_WITHDRAWAL => 'Owner withdrawal',
            self::PETTY_CASH => 'Petty cash',
            self::DROP => 'Cash drop',
            self::GCASH_CASH_IN => 'GCash cash-in',
            self::GCASH_CASH_OUT => 'GCash cash-out',
            self::PAID_IN => 'Paid in',
            self::PAID_OUT => 'Paid out',
        };
    }
}
