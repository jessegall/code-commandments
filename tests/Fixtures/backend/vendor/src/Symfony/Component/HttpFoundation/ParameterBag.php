<?php

namespace Symfony\Component\HttpFoundation;

class ParameterBag implements \IteratorAggregate, \Countable, \Traversable
{
    protected array $parameters;
    public function __construct(array $parameters = array (
)) {}
    public function all(?string $key = NULL): array {}
    public function keys(): array {}
    public function replace(array $parameters = array (
)): void {}
    public function add(array $parameters = array (
)): void {}
    public function get(string $key, mixed $default = NULL): mixed {}
    public function set(string $key, mixed $value): void {}
    public function has(string $key): bool {}
    public function remove(string $key): void {}
    public function getAlpha(string $key, string $default = ''): string {}
    public function getAlnum(string $key, string $default = ''): string {}
    public function getDigits(string $key, string $default = ''): string {}
    public function getString(string $key, string $default = ''): string {}
    public function getInt(string $key, int $default = 0): int {}
    public function getBoolean(string $key, bool $default = false): bool {}
    public function getEnum(string $key, string $class, ?\BackedEnum $default = NULL): ?\BackedEnum {}
    public function filter(string $key, mixed $default = NULL, int $filter = \FILTER_DEFAULT, mixed $options = array (
)): mixed {}
    public function getIterator(): \ArrayIterator {}
    public function count(): int {}
}
