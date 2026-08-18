<?php

namespace Database\Seeders;

use App\Enums\StatusEnum;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * What a sari-sari actually spends on outside stock. These are the lines
     * that turn gross margin into real profit.
     *
     * Not pruned, unlike the permission catalogue: a category may already be
     * referenced by a recorded expense, and deleting it would orphan history.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'Kuryente' => 'Electricity',
        'Tubig' => 'Water',
        'Upa' => 'Rent',
        'Transportation' => 'Fare and delivery to fetch stock',
        'Plastic bags' => 'Bags and packaging',
        'Yelo' => 'Ice for drinks',
        'Allowance' => 'Staff allowance and wages',
        'Delivery fee' => 'Supplier delivery charges',
        'Spoilage' => 'Written-off goods paid for in cash',
        'Repairs' => 'Freezer, shelving, and equipment',
        'Permits' => 'Barangay and business permits',
        'Miscellaneous' => 'Anything not covered above',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            // firstOrCreate, not create: db:seed must stay re-runnable, and
            // `expense_categories.name` is globally unique now that the table
            // is shared across every store.
            ExpenseCategory::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'status' => StatusEnum::ACTIVE],
            );
        }
    }
}
