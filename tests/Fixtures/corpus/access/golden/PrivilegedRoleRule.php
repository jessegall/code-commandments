<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Grants a privileged role unconditional access, mutating or not.
 */
final class PrivilegedRoleRule implements AccessRule
{
    public function allows(AccessRequest $request): bool
    {
        return $request->actor->role->isPrivileged();
    }
}
