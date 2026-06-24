<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Grants access when the actor explicitly holds the requested permission.
 */
final class GrantedPermissionRule implements AccessRule
{
    public function allows(AccessRequest $request): bool
    {
        return $request->actor->holds($request->permission);
    }
}
