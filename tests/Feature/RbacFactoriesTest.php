<?php

use App\Enums\StatusEnum;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates a role without an organization', function () {
    $role = Role::factory()->create();

    expect($role->exists)->toBeTrue()
        ->and($role->is_system)->toBeFalse()
        ->and($role->status)->toBe(StatusEnum::ACTIVE);

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'slug' => $role->slug]);
});

it('creates a system role through the system state', function () {
    $role = Role::factory()->system()->create();

    expect($role->is_system)->toBeTrue();
    expect(Role::system()->pluck('id'))->toContain($role->id);
});

it('does not collide on the globally unique slug', function () {
    $roles = Role::factory()->count(5)->create();

    expect($roles->pluck('slug')->unique())->toHaveCount(5);
});

it('creates a permission with its group', function () {
    $permission = Permission::factory()->create();

    expect($permission->permissionGroup)->toBeInstanceOf(PermissionGroup::class);
    expect($permission->permissionGroup->permissions->pluck('id'))->toContain($permission->id);
});

it('syncs permissions onto a role', function () {
    $role = Role::factory()->create();
    $permissions = Permission::factory()->count(3)->create();

    $role->permissions()->sync($permissions->pluck('id'));

    expect($role->refresh()->permissions)->toHaveCount(3);
    expect($permissions->first()->refresh()->roles->pluck('id'))->toContain($role->id);
});

it('rejects a duplicate role permission pair', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $role->permissions()->attach($permission->id);

    expect(fn () => $role->permissions()->attach($permission->id))
        ->toThrow(UniqueConstraintViolationException::class);
});
