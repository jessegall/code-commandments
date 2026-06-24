<?php

namespace App\Access;

/**
 * A composable predicate that decides whether a request is allowed.
 */
interface AccessRule
{
    public function allows(AccessRequest $request): bool;
}
