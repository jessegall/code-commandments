<?php
namespace Acme\Notify\Adopt;

final class InviteCollaborator
{
    public function __construct(private UserDirectory $users) {}

    public function invite(string $email): string
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return 'unknown';
        }
        return $user->email;
    }
}
