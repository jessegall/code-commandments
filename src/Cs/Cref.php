<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\PhpTypes\Option;

/**
 * A `cref` a documentation comment names — as written, and the symbol the compiler resolved it to, when it
 * resolved. One that did not resolve says where it points: the longest qualifier of it that resolves, whether
 * the project declares that one, and whether the names in scope are blind — the compiler missing a type or
 * namespace there.
 */
final readonly class Cref
{
    public function __construct(
        public string $text,
        private ?string $symbol,
        private ?string $owner,
        private bool $ownedHere,
        private bool $blind,
    ) {}

    /**
     * @param  array<string, mixed>  $written
     */
    public static function fromContract(array $written): self
    {
        return new self(
            (string) $written['text'],
            isset($written['symbol']) ? (string) $written['symbol'] : null,
            isset($written['owner']) ? (string) $written['owner'] : null,
            (bool) $written['ownedHere'],
            (bool) $written['blind'],
        );
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

    /**
     * Does this name something the project should declare and does not — resolving to nothing, under a
     * qualifier the project declares itself, or with no qualifier that resolves while every `using` in scope
     * does? A name gone from a library, or one a missing reference may hold, cannot be judged from here.
     */
    public function isDangling(): bool
    {
        if ($this->symbol !== null) {
            return false;
        }

        return $this->ownedHere || ($this->owner === null && ! $this->blind);
    }
}
