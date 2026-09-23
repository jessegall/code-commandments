<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * How much of a read the compiler resolved: of the calls the bridge wrote, how many name their target.
 */
final readonly class Resolution
{
    public function __construct(
        public int $calls,
        public int $resolved,
    ) {}

    /**
     * @param  array<string, mixed>  $written  the resolution as the bridge's contract writes it
     */
    public static function fromContract(array $written): self
    {
        return new self((int) $written['calls'], (int) $written['resolved']);
    }
}
