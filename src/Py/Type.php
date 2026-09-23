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
    ) {}

    /**
     * @param  array<string, mixed>  $contract  one entry of the bridge's `types` list
     */
    public static function fromContract(array $contract): self
    {
        return new self((string) $contract['type'], isset($contract['class']) ? (string) $contract['class'] : null, (bool) $contract['nullable']);
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
}
