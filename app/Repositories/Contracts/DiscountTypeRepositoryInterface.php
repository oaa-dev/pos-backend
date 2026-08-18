<?php

namespace App\Repositories\Contracts;

use App\Models\DiscountType;

interface DiscountTypeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Resolve an applicable type by slug.
     *
     * Only active types resolve: an owner who switches a promo off expects it
     * to stop being applicable immediately, and a sale quoting a retired slug
     * must fail loudly rather than discount nothing and look correct.
     */
    public function findApplicable(string $slug): ?DiscountType;
}
