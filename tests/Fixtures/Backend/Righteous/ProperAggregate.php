<?php

namespace App\Aggregates;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class UserAggregate extends AggregateRoot
{
    // Righteous: recordThat is called inside the aggregate, not from outside
    public function createUser(string $name, string $email): self
    {
        $this->recordThat(new UserCreated($name, $email));

        return $this;
    }

    public function updateUser(string $name): self
    {
        $this->recordThat(new UserUpdated($name));

        return $this;
    }
}
