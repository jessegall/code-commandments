<?php

declare(strict_types=1);

namespace App\Access;

/**
 * An unkeyed, iterate-only collection of the permissions an actor holds.
 */
final class PermissionSet
{
    /**
     * @var list<Permission>
     */
    private array $permissions = [];

    public static function empty(): self
    {
        return new self();
    }

    public static function of(Permission ...$permissions): self
    {
        $set = new self();

        foreach ($permissions as $permission) {
            $set->add($permission);
        }

        return $set;
    }

    public function add(Permission $permission): self
    {
        if (! $this->has($permission)) {
            $this->permissions[] = $permission;
        }

        return $this;
    }

    public function has(Permission $permission): bool
    {
        return in_array($permission, $this->permissions, strict: true);
    }

    /**
     * @return list<Permission>
     */
    public function all(): array
    {
        return $this->permissions;
    }

    public function isEmpty(): bool
    {
        return $this->permissions === [];
    }
}
