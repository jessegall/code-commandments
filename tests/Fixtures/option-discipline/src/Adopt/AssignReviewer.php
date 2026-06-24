<?php
namespace Acme\Notify\Adopt;

final class AssignReviewer
{
    public function __construct(private UserDirectory $users) {}

    public function assign(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }
        echo $user->email;
    }
}
