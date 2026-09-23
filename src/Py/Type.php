<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\PhpTypes\Option;

/**
 * The type mypy resolved for one expression — as mypy writes it (`str | None`), and the class it is
 * an instance of when it is one, or one class or `None`.
 */
final readonly class Type
{
    public function __construct(
        public string $written,
        private ?string $class,
        public bool $nullable,
        private ?string $constructs = null,
    ) {}

    /**
     * @param  array<string, mixed>  $contract  one entry of the bridge's `types` list
     */
    public static function fromContract(array $contract): self
    {
        return new self((string) $contract['type'], isset($contract['class']) ? (string) $contract['class'] : null, (bool) $contract['nullable'], isset($contract['constructs']) ? (string) $contract['constructs'] : null);
    }

    /**
     * The class this is an instance of — `shop.cart.Cart` — none for a type that is no single class.
     *
     * @return Option<string>
     */
    public function className(): Option
    {
        return Option::fromNullable($this->class);
    }

    /**
     * The class this expression names, so that calling it builds one — `builtins.str` for `str`,
     * `shop.money.Money` for the `Money` in `Money.of` — none for anything else.
     *
     * @return Option<string>
     */
    public function constructedClass(): Option
    {
        return Option::fromNullable($this->constructs);
    }
}
