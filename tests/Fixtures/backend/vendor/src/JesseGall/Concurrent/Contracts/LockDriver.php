<?php

namespace JesseGall\Concurrent\Contracts;

interface LockDriver
{
    public function acquire(string $key, int $ttl, int $timeout, callable $callback): mixed;
}
