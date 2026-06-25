<?php

namespace Shop\Support\Concerns;

trait HasUuid
{
    public function uuid(): string
    {
        return (string) ($this->attributes['uuid'] ?? '');
    }
}
