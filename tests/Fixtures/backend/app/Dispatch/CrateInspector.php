<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Sins\Backend\RedundantElse;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads the labels stuck on a crate.
 */
final class CrateInspector
{
    /**
     * @param  array<string, list<string>>  $labels  kind → the labels of that kind
     */
    public function __construct(private readonly array $labels) {}

    /**
     * The flag in its commonest disguise: `$kind` was required, then widened to nullable so that
     * leaving it out means "all of them". Two questions behind one name, and the call site that asks
     * the second one says nothing at all — `labels()` — which is even less than a bare `true`.
     */
    #[Sinful(FlagArgument::class)]
    #[Sinful(RedundantElse::class)]
    public function labels(?string $kind = null): array
    {
        if ($kind === null) {
            return array_merge(...array_values($this->labels));
        } else {
            return $this->labels[$kind] ?? [];
        }
    }

    /**
     * The same two questions, each with its own name, so the call site says which it asked.
     */
    #[Fixed(FlagArgument::class)]
    public function allLabels(): array
    {
        return array_merge(...array_values($this->labels));
    }

    #[Fixed(FlagArgument::class)]
    public function labelsOfKind(string $kind): array
    {
        return $this->labels[$kind] ?? [];
    }

    /**
     * A nullable that is a PRECONDITION, not a mode: absence is answered by a guard and the one
     * behaviour follows. Nothing here is two methods.
     */
    #[Righteous(FlagArgument::class)]
    public function firstLabel(?string $kind): ?string
    {
        if ($kind === null) {
            return null;
        }

        return $this->labels[$kind][0] ?? null;
    }
}
