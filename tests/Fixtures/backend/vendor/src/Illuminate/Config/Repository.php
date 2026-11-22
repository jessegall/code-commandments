<?php

namespace Illuminate\Config;

class Repository implements \ArrayAccess, \Illuminate\Contracts\Config\Repository
{
    use \Illuminate\Support\Traits\Macroable;
    protected $items = array (
);
    public function __construct(array $items = array (
)) {}
    /** @return bool */
    public function has($key) {}
    /** @return mixed */
    public function get($key, $default = NULL) {}
    /** @return array<string,mixed> */
    public function getMany($keys) {}
    public function string(string $key, $default = NULL): string {}
    public function integer(string $key, $default = NULL): int {}
    public function float(string $key, $default = NULL): float {}
    public function boolean(string $key, $default = NULL): bool {}
    public function array(string $key, $default = NULL): array {}
    public function collection(string $key, $default = NULL): \Illuminate\Support\Collection {}
    /** @return void */
    public function set($key, $value = NULL) {}
    /** @return void */
    public function prepend($key, $value) {}
    /** @return void */
    public function push($key, $value) {}
    /** @return array */
    public function all() {}
    public function offsetExists($key): bool {}
    public function offsetGet($key): mixed {}
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
}
