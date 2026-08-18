<?php

namespace App\Services;

use App\Data\BranchData;
use App\Enums\StatusEnum;
use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class BranchService extends BaseService
{
    public function __construct(
        protected readonly BranchRepositoryInterface $branchRepository,
        protected readonly AddressService $addressService,
    ) {
        parent::__construct($branchRepository);
    }

    /**
     * The select list, not the Branches screen. Narrowing lives in the
     * repository, where the actor is known.
     *
     * @return Collection<int, Branch>
     */
    public function dropdown(?int $storeId = null): Collection
    {
        return $this->branchRepository->dropdown($storeId);
    }

    public function store(BranchData $data): Branch
    {
        return DB::transaction(function () use ($data) {
            $name = $data->name instanceof Optional ? '' : $data->name;

            $branch = $this->branchRepository->create([
                'store_id' => $data->store_id instanceof Optional ? null : $data->store_id,
                'name' => $name,
                'code' => $this->resolveCode($data, $name),
                'phone' => $this->value($data->phone),
                'status' => $data->status instanceof Optional ? StatusEnum::ACTIVE : $data->status,
                'opened_at' => $this->value($data->opened_at),
            ]);

            if (! $data->address instanceof Optional) {
                $this->addressService->upsertFor($branch, $data->address);
            }

            return $branch->load('address')->loadCount('users');
        });
    }

    public function updateBranch(Branch $branch, BranchData $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $values = array_filter([
                'store_id' => $this->value($data->store_id),
                'name' => $this->value($data->name),
                'code' => $this->value($data->code),
                'phone' => $this->value($data->phone),
                'status' => $this->value($data->status),
                'opened_at' => $this->value($data->opened_at),
            ], fn ($value) => $value !== null);

            if ($values !== []) {
                $branch = $this->branchRepository->update($branch, $values);
            }

            if (! $data->address instanceof Optional) {
                $this->addressService->upsertFor($branch, $data->address);
            }

            return $branch->load('address')->loadCount('users');
        });
    }

    public function assignUser(Branch $branch, User $user, bool $isPrimary = false): Branch
    {
        return $this->branchRepository
            ->assignUser($branch, $user, $isPrimary)
            ->load('users')
            ->loadCount('users');
    }

    public function unassignUser(Branch $branch, User $user): Branch
    {
        return $this->branchRepository
            ->unassignUser($branch, $user)
            ->load('users')
            ->loadCount('users');
    }

    /**
     * Branch codes are what a tindera types and what receipts carry, so they
     * stay short and derived from the name unless one is supplied.
     */
    private function resolveCode(BranchData $data, string $name): string
    {
        if (! $data->code instanceof Optional && $data->code !== null) {
            return Str::upper($data->code);
        }

        $base = Str::upper(Str::limit(Str::slug($name, ''), 6, '')) ?: Str::upper(Str::random(6));

        // `branches.code` is unique and two branches can easily share a name
        // ("Aling Nena's" in two barangays), so a derived code has to be
        // checked rather than assumed.
        $code = $base;
        $suffix = 2;

        while ($this->branchRepository->findByCode($code) !== null) {
            $code = Str::limit($base, 4, '').$suffix;
            $suffix++;
        }

        return $code;
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof Optional ? null : $value;
    }
}
