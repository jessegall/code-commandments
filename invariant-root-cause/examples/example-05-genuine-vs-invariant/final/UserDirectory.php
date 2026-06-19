<?php

declare(strict_types=1);

namespace Directory;

use Support\Option;

final class UserDirectory
{
    /** @var array<int, User> */
    private array $byId = [];

    /** @var array<string, User> */
    private array $byEmail = [];

    public function add(User $user): void
    {
        $this->byId[$user->id] = $user;
        $this->byEmail[strtolower($user->email)] = $user;
    }

    /**
     * Invariant: an id is a foreign key — it must resolve. A miss throws.
     */
    public function getById(int $id): User
    {
        return $this->byId[$id] ?? throw UserNotFoundException::forId($id);
    }

    /**
     * Genuine absence: a search-by-email may legitimately find nothing, so the
     * result is an Option<User>. This is Option doing its actual job — the
     * caller is *meant* to branch on it. No invariant, no throw.
     *
     * @return Option<User>
     */
    public function findByEmail(string $email): Option
    {
        return Option::fromValue($this->byEmail[strtolower($email)] ?? null);
    }
}
