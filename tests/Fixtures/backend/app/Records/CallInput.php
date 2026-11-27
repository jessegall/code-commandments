<?php

namespace Shop\Records;

final readonly class CallInput
{
    public function __construct(public string $token) {}
}
