<?php

namespace JesseGall\Concurrent\Contracts;

interface CacheDriver
{
    public function get(string $key, mixed $default = NULL): mixed;
    public function put(string $key, mixed $value, ?int $ttl): void;
    public function forget(string $key): void;
}
