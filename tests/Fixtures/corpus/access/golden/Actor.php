<?php

declare(strict_types=1);

namespace App\Access;

/**
 * The authenticated subject whose access is being decided.
 */
final readonly class Actor
{
    public function __construct(
        public string $id,
        public Role $role,
        public PermissionSet $permissions,
    ) {}

    public function holds(Permission $permission): bool
    {
        return $this->permissions->has($permission);
    }
}
