<?php

namespace JesseGall\Concurrent;

class Concurrent implements \ArrayAccess, \IteratorAggregate, \Traversable
{
    use \JesseGall\Concurrent\ForwardsCallsToTarget;
    private static ?\JesseGall\Concurrent\Contracts\CacheDriver $defaultCache = NULL;
    private static ?\JesseGall\Concurrent\Contracts\LockDriver $defaultLock = NULL;
    private readonly \JesseGall\Concurrent\Contracts\KeyResolver $keyResolver;
    private readonly \JesseGall\Concurrent\CallableResolver $callableResolver;
    private readonly \JesseGall\Concurrent\ReadonlyMethodInvoker $readonlyInvoker;
    private readonly \JesseGall\Concurrent\ConcurrentValueValidator $validator;
    private readonly mixed $default;
    private readonly ?int $ttl;
    private readonly int $lockDuration;
    private readonly bool $readLock;
    private readonly \JesseGall\Concurrent\Contracts\CacheDriver $cacheDriver;
    private readonly \JesseGall\Concurrent\Contracts\LockDriver $lockDriver;
    private bool $isLocked = false;
    private string $key;
    public function __construct(?string $key = NULL, mixed $default = NULL, ?int $ttl = NULL, ?callable $validator = NULL, ?\JesseGall\Concurrent\Contracts\CacheDriver $cache = NULL, ?\JesseGall\Concurrent\Contracts\LockDriver $lock = NULL, int $lockDuration = 10, bool $readLock = false) {}
    public static function useCache(\JesseGall\Concurrent\Contracts\CacheDriver $cache): void {}
    public static function useLock(\JesseGall\Concurrent\Contracts\LockDriver $lock): void {}
    public static function resetDrivers(): void {}
    /** @return TValue|void */
    public function __invoke(mixed $value = NULL) {}
    public function __call(string $name, array $arguments) {}
    public function __get(string $name) {}
    public function __set(string $name, mixed $value): void {}
    public function __isset(string $name): bool {}
    public function __unset(string $name): void {}
    public function offsetExists(mixed $offset): bool {}
    public function offsetGet(mixed $offset): mixed {}
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
    public function getIterator(): \Traversable {}
    private function get(): mixed {}
    private function getProperty(string $key): mixed {}
    private function set(mixed $value = NULL): void {}
    private function setProperty(?string $key, mixed $value): void {}
    private function forget(): void {}
    private function lock(?callable $callback = NULL): mixed {}
    private function resolveDefaultValue(): mixed {}
    private function call(string $name, array $arguments): mixed {}
    private function unset(string $property): void {}
    private function isset(string $property): bool {}
    private function resolveDefaultCache(): \JesseGall\Concurrent\Contracts\CacheDriver {}
    private function resolveDefaultLock(): \JesseGall\Concurrent\Contracts\LockDriver {}
    private function hasLaravel(): bool {}
}
