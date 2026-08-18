<?php

namespace Database\Seeders;

use App\Models\DiscountType;
use App\Models\SaleDiscount;
use Illuminate\Database\Seeder;

class DiscountTypeSeeder extends Seeder
{
    /**
     * The six kinds that used to be SaleDiscountTypeEnum cases.
     *
     * `senior` and `pwd` seed as system rows: the 20% and the ID logbook are
     * statutory, so CRUD may rename them or switch them off but not re-rate
     * them. The rest are ordinary rows the owner can edit or delete.
     *
     * Not pruned, unlike the permission catalogue — a type may already be
     * referenced by a completed sale, and removing it would orphan the record
     * of what was actually given.
     *
     * @var list<array{slug:string, name:string, percentage:string|null, requires_identification:bool, is_system:bool}>
     */
    private const TYPES = [
        [
            'slug' => 'senior',
            'name' => 'Senior Citizen',
            'percentage' => '20.00',
            'requires_identification' => true,
            'is_system' => true,
        ],
        [
            'slug' => 'pwd',
            'name' => 'PWD',
            'percentage' => '20.00',
            'requires_identification' => true,
            'is_system' => true,
        ],
        [
            'slug' => 'manual',
            'name' => 'Manual',
            'percentage' => null,
            'requires_identification' => false,
            'is_system' => false,
        ],
        [
            'slug' => 'promotional',
            'name' => 'Promotional',
            'percentage' => null,
            'requires_identification' => false,
            'is_system' => false,
        ],
        [
            'slug' => 'employee',
            'name' => 'Employee',
            'percentage' => null,
            'requires_identification' => false,
            'is_system' => false,
        ],
        [
            'slug' => 'wholesale',
            'name' => 'Wholesale',
            'percentage' => null,
            'requires_identification' => false,
            'is_system' => false,
        ],
    ];

    /**
     * Each store gets its own six rows, including its own protected `senior`
     * and `pwd` — the statute applies to every shop, so every shop needs the
     * pair, and one store switching off `wholesale` must not touch another's.
     */
    public function run(): void
    {
        foreach (self::TYPES as $type) {
            // updateOrCreate keyed on slug: db:seed must stay re-runnable, and
            // re-running has to restore a statutory rate someone edited in the
            // database by hand.
            DiscountType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'percentage' => $type['percentage'],
                    'requires_identification' => $type['requires_identification'],
                    'is_system' => $type['is_system'],
                ],
            );
        }

        $this->backfillSaleDiscounts();
    }

    /**
     * Point historical sale_discounts rows at their new type.
     *
     * `sale_discounts.type` already stores exactly the slug seeded above, so
     * this matches without a lookup table. `type` stays as written — it is the
     * record of what was applied at the time, and must not move if a type is
     * later renamed.
     *
     * `sale_discounts` carries no `store_id` — it reaches its tenant through
     * `sale.branch`. Without that constraint every store's re-seed would
     * re-point every other store's history at its own `senior` row, which is
     * the multi-tenant version of the bug this backfill exists to fix. The
     * `whereHas` picks up `Branch`'s tenant scope for free.
     *
     * Skipped outright when nothing is unlinked, which is every provisioning
     * of a new store.
     */
    private function backfillSaleDiscounts(): void
    {
        if (! SaleDiscount::whereNull('discount_type_id')->exists()) {
            return;
        }

        DiscountType::query()->each(function (DiscountType $type) {
            SaleDiscount::query()
                ->whereNull('discount_type_id')
                ->where('type', $type->slug)
                ->whereHas('sale', fn ($sale) => $sale->whereHas('branch'))
                ->update(['discount_type_id' => $type->id]);
        });
    }
}
