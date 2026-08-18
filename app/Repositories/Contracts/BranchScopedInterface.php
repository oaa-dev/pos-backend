<?php

namespace App\Repositories\Contracts;

/**
 * Marks a repository whose rows belong to a branch.
 *
 * BaseRepository::query() narrows the listing to the branches the current user
 * is assigned to unless they hold `branches.view-all`. Every repository whose
 * model carries a branch must implement this — a missing implementation leaks
 * one branch's rows to another, which is a data breach rather than a cosmetic
 * bug.
 *
 * Note that this covers **listings only**. Endpoints that resolve a single
 * record through route-model binding never reach the repository, and need
 * BranchPolicy (or an equivalent check) instead.
 */
interface BranchScopedInterface
{
    /**
     * The column on this repository's model that holds the branch id —
     * `branch_id` for most, `id` for the branches table itself.
     */
    public function branchColumn(): string;
}
