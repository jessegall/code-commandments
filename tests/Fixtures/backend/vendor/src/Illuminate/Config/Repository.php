<?php

namespace Illuminate\Config;

class Repository implements \ArrayAccess, \Illuminate\Contracts\Config\Repository
{
    use \Illuminate\Support\Traits\Macroable;
    protected $items = array (
);
    public function __construct(array $items = array (
)) {}
    public function has($key) {}
    public function get($key, $default = NULL) {}
    public function getMany($keys) {}
    public function string(string $key, $default = NULL): string {}
    public function integer(string $key, $default = NULL): int {}
    public function float(string $key, $default = NULL): float {}
    public function boolean(string $key, $default = NULL): bool {}
    public function array(string $key, $default = NULL): array {}
    public function collection(string $key, $default = NULL): \Illuminate\Support\Collection {}
    public function set($key, $value = NULL) {}
    public function prepend($key, $value) {}
    public function push($key, $value) {}
    public function all() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
}
