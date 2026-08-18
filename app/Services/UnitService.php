<?php

namespace App\Services;

use App\Data\UnitData;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class UnitService extends BaseService
{
    public function __construct(
        protected readonly UnitRepositoryInterface $unitRepository
    ) {
        parent::__construct($unitRepository);
    }

    /**
     * The select list, not the Units screen.
     *
     * @return Collection<int, Unit>
     */
    public function dropdown(): Collection
    {
        return $this->unitRepository->dropdown();
    }

    public function store(UnitData $data): Unit
    {
        $name = $data->name instanceof Optional ? '' : $data->name;

        return $this->unitRepository->create([
            'name' => $name,
            'abbreviation' => $this->abbreviation($data, $name),
        ]);
    }

    public function updateUnit(Unit $unit, UnitData $data): Unit
    {
        $values = array_filter([
            'name' => $data->name instanceof Optional ? null : $data->name,
            'abbreviation' => $data->abbreviation instanceof Optional ? null : $data->abbreviation,
        ], fn ($value) => $value !== null);

        return $values === [] ? $unit : $this->unitRepository->update($unit, $values);
    }

    /**
     * Retire a unit.
     *
     * Soft delete, always. `products.base_unit_id` is constrained without an
     * onDelete clause, so a hard delete RESTRICTs and surfaces a raw FK error;
     * and a product sold in this unit still has to render its own history.
     * Every relation reading a unit uses withTrashed() for that reason.
     */
    public function retire(Unit $unit): void
    {
        $this->unitRepository->delete($unit);
    }

    /**
     * Derived like `BranchService` derives a branch code: taken from the name,
     * then checked rather than assumed, because two units can easily shorten
     * to the same thing ("sako" and "sakong maliit").
     */
    private function abbreviation(UnitData $data, string $name): string
    {
        if (! $data->abbreviation instanceof Optional && $data->abbreviation !== null) {
            return $data->abbreviation;
        }

        $base = Str::lower(Str::limit(Str::slug($name, ''), 4, '')) ?: Str::lower(Str::random(3));

        $abbreviation = $base;
        $suffix = 2;

        while ($this->unitRepository->findBy('abbreviation', $abbreviation) !== null) {
            $abbreviation = Str::limit($base, 3, '').$suffix;
            $suffix++;
        }

        return $abbreviation;
    }
}
