<?php

namespace App\Enums;

/**
 * The three roles the application ships with.
 *
 * Backed by **slug**, not by row id. `SystemRoleSeeder` upserts these keyed on
 * `slug`, so ids are only stable on a database that was seeded once into an
 * empty table — an int-backed enum silently pointed at the wrong role after
 * any reseed that changed insertion order.
 *
 * Roles are global and not creatable through the API, so this list is the
 * whole set.
 */
enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case OWNER = 'owner';
    case TINDERA = 'tindera';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
