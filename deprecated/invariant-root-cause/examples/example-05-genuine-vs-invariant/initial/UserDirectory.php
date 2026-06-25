<?php

declare(strict_types=1);

namespace Directory;

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
     * SMELL: ids are foreign keys / session ids — a miss is a bug. Returning
     * `?User` (via a no-op `?? null`) launders that invariant into absence.
     */
    public function getById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * NOT a smell to "fix" by throwing: email is free-text search input, so a
     * miss is a normal, expected outcome. Returning null is the SYMPTOM the
     * linter should push toward `Option` — and that is correct, because there
     * is no invariant here. (final/ returns Option<User>.)
     */
    public function findByEmail(string $email): ?User
    {
        return $this->byEmail[strtolower($email)] ?? null;
    }
}
