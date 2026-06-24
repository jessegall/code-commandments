<?php

namespace App\Access;

/**
 * The fully-typed question put to the policy: may this actor do this?
 */
final readonly class AccessRequest
{
    public function __construct(
        public Actor $actor,
        public Permission $permission,
    ) {}
}
