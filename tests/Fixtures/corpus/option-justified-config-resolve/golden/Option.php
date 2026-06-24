<?php

namespace App\OptionCorpus\OptionJustifiedConfigResolve;

/** Stand-in marker for JesseGall\PhpTypes\Option used by this corpus slice. */
final class Option
{
    private function __construct(
        private readonly bool $present,
        private readonly mixed $value,
    ) {}

    public static function some(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function none(): self
    {
        return new self(false, null);
    }

    public static function fromNullable(mixed $value): self
    {
        return $value === null ? self::none() : self::some($value);
    }

    public function isSome(): bool
    {
        return $this->present;
    }

    public function map(callable $fn): self
    {
        return $this->present ? self::some($fn($this->value)) : $this;
    }

    /** Take this value if present, otherwise fall through to the alternative Option. */
    public function orElse(callable $alternative): self
    {
        return $this->present ? $this : $alternative();
    }

    public function getOrElse(mixed $default): mixed
    {
        return $this->present ? $this->value : $default;
    }

    public function getOrThrow(callable $throw): mixed
    {
        if (! $this->present) {
            throw $throw();
        }

        return $this->value;
    }
}
