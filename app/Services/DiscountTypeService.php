<?php

namespace App\Services;

use App\Data\DiscountTypeData;
use App\Enums\StatusEnum;
use App\Models\DiscountType;
use App\Repositories\Contracts\DiscountTypeRepositoryInterface;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

/**
 * Managing which discounts exist, as distinct from applying one.
 *
 * The whole point of moving discounts out of an enum is that the owner can add
 * a promo without a migration. The counterweight is that `senior` and `pwd`
 * are not the owner's to redefine — the 20% and the ID logbook are statutory,
 * so those rows are protected here rather than trusted to the caller.
 */
class DiscountTypeService extends BaseService
{
    public function __construct(
        protected readonly DiscountTypeRepositoryInterface $discountTypes
    ) {
        parent::__construct($discountTypes);
    }

    public function store(DiscountTypeData $data): DiscountType
    {
        $name = $data->name instanceof Optional ? '' : $data->name;

        return $this->discountTypes->create([
            'name' => $name,
            'slug' => $data->slug instanceof Optional || $data->slug === null
                ? $this->uniqueSlug($name)
                : $data->slug,
            'percentage' => $data->percentage instanceof Optional ? null : $data->percentage,
            'requires_identification' => $data->requires_identification instanceof Optional
                ? false
                : $data->requires_identification,
            'status' => $data->status instanceof Optional ? StatusEnum::ACTIVE : $data->status,
            // Never settable through the API, and absent from DiscountTypeData
            // for that reason. A row becomes statutory by being seeded.
            'is_system' => false,
        ]);
    }

    public function updateType(DiscountType $type, DiscountTypeData $data): DiscountType
    {
        $values = $this->attributes($data);

        // Renaming `senior` to "Senior Citizen (RA 9994)" is housekeeping, and
        // switching it off is a legitimate thing to do while a terminal is
        // misconfigured. Re-rating it is neither: every sale afterwards would
        // be wrong in a way no later report could detect.
        //
        // Unchanged by the DTO conversion — `attributes()` drops `Optional`
        // fields first, so `array_key_exists` still means "was submitted".
        if ($type->is_system) {
            foreach (DiscountType::PROTECTED_FIELDS as $field) {
                if (array_key_exists($field, $values) && (string) $values[$field] !== (string) $type->{$field}) {
                    throw new \InvalidArgumentException(
                        "The statutory {$type->slug} discount cannot have its {$field} changed."
                    );
                }
            }

            $values = collect($values)->except(DiscountType::PROTECTED_FIELDS)->all();
        }

        if ($values === []) {
            return $type;
        }

        return $this->discountTypes->update($type, $values);
    }

    /**
     * `Optional` means "not submitted" and is dropped; an explicit null is a
     * real value — clearing the percentage so the amount is keyed in at the
     * till — and is kept.
     *
     * @return array<string, mixed>
     */
    private function attributes(DiscountTypeData $data): array
    {
        return array_filter([
            'name' => $data->name,
            'slug' => $data->slug,
            'percentage' => $data->percentage,
            'requires_identification' => $data->requires_identification,
            'status' => $data->status,
        ], fn ($value) => ! $value instanceof Optional);
    }

    /**
     * Retire a type.
     *
     * Statutory rows cannot be retired at all — a store that cannot record a
     * senior discount has no way to ring one up correctly. Everything else
     * soft-deletes, so completed sales keep pointing at what was applied.
     */
    public function retire(DiscountType $type): void
    {
        if ($type->is_system) {
            throw new \InvalidArgumentException(
                "The statutory {$type->slug} discount cannot be deleted. Deactivate it instead."
            );
        }

        $this->discountTypes->delete($type);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(6));

        $slug = $base;
        $suffix = 2;

        while ($this->discountTypes->findBy('slug', $slug) !== null) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
