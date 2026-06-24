<?php
namespace Acme\Notify\Adopt;

final class UserDirectory
{
    /** @var array<string, User> */
    private array $byEmail = [];

    // CASE A: decides nothingness with a bare null; two callers each branch on it.
    public function findByEmail(string $email): User|null
    {
        foreach ($this->byEmail as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }
}
