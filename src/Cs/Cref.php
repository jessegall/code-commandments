<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\PhpTypes\Option;

/**
 * A `cref` a documentation comment names — as written, and the symbol the compiler resolved it to, when it
 * resolved.
 */
final readonly class Cref
{
    public function __construct(
        public string $text,
        private ?string $symbol,
    ) {}

    /**
     * @param  array<string, mixed>  $written
     */
    public static function fromContract(array $written): self
    {
        return new self((string) $written['text'], isset($written['symbol']) ? (string) $written['symbol'] : null);
    }

    /**
     * The symbol this names, as the compiler resolved it — none when it names nothing the compilation holds.
     *
     * @return Option<string>
     */
    public function symbol(): Option
    {
        return Option::fromNullable($this->symbol);
    }
}
