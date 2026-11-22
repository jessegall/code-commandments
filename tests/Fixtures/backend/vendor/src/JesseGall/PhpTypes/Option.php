<?php

namespace JesseGall\PhpTypes;

final class Option implements \IteratorAggregate, \Traversable
{
    private readonly bool $isSome;
    private readonly mixed $value;
    private function __construct(bool $isSome, mixed $value) {}
    public static function some(mixed $value): \JesseGall\PhpTypes\Option {}
    public static function none(): \JesseGall\PhpTypes\Option {}
    public static function fromNullable(mixed $value): \JesseGall\PhpTypes\Option {}
    public static function fromTruthy(mixed $value): \JesseGall\PhpTypes\Option {}
    public function isSome(): bool {}
    public function isNone(): bool {}
    public function isSomeAnd(callable $predicate): bool {}
    public function isNoneOr(callable $predicate): bool {}
    public function unwrap(): mixed {}
    public function expect(string $message): mixed {}
    public function unwrapOr(mixed $default): mixed {}
    public function unwrapOrElse(callable $default): mixed {}
    public function toNullable(): mixed {}
    public function map(callable $fn): \JesseGall\PhpTypes\Option {}
    public function mapOr(mixed $default, callable $fn): mixed {}
    public function mapOrElse(callable $default, callable $fn): mixed {}
    public function inspect(callable $fn): \JesseGall\PhpTypes\Option {}
    public function filter(callable $predicate): \JesseGall\PhpTypes\Option {}
    public function and(\JesseGall\PhpTypes\Option $optb): \JesseGall\PhpTypes\Option {}
    public function andThen(callable $fn): \JesseGall\PhpTypes\Option {}
    public function or(\JesseGall\PhpTypes\Option $optb): \JesseGall\PhpTypes\Option {}
    public function orElse(callable $fn): \JesseGall\PhpTypes\Option {}
    public function xor(\JesseGall\PhpTypes\Option $optb): \JesseGall\PhpTypes\Option {}
    public function zip(\JesseGall\PhpTypes\Option $other): \JesseGall\PhpTypes\Option {}
    public function flatten(): \JesseGall\PhpTypes\Option {}
    public function equals(\JesseGall\PhpTypes\Option $other): bool {}
    public function getIterator(): \Traversable {}
}
