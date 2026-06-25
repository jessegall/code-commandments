<?php

declare(strict_types=1);

namespace Account;

final class UserNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("No user with id {$id}.");
    }
}
