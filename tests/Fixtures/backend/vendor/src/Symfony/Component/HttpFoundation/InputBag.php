<?php

namespace Symfony\Component\HttpFoundation;

final class InputBag extends \Symfony\Component\HttpFoundation\ParameterBag
{
    public function get(string $key, mixed $default = NULL): string|int|float|bool|null {}
    public function replace(array $inputs = array (
)): void {}
    public function add(array $inputs = array (
)): void {}
    public function set(string $key, mixed $value): void {}
    public function getEnum(string $key, string $class, ?\BackedEnum $default = NULL): ?\BackedEnum {}
    public function getString(string $key, string $default = ''): string {}
    public function filter(string $key, mixed $default = NULL, int $filter = \FILTER_DEFAULT, mixed $options = array (
)): mixed {}
}
