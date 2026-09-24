<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Hooks\SinMark;

/**
 * What one moment changed in the announced sins: the sins it found, and the ones it proved repented.
 */
final readonly class Settlement
{
    /**
     * @param  list<SinMark>  $found
     * @param  array<string, string>  $resolved  sin id => how the sin was shown when it was found
     */
    public function __construct(
        public array $found,
        public array $resolved,
    ) {}

    /**
     * The events the journal shows for it: sin-found and sin-resolved, each only when there is one.
     *
     * @return list<JournalRaise>
     */
    public function raises(string $root): array
    {
        return array_values(array_filter([
            $this->found === [] ? null : new JournalRaise('sin-found', implode("\n", array_map(static fn (SinMark $mark): string => $mark->shownFrom($root), $this->found))),
            $this->resolved === [] ? null : new JournalRaise('sin-resolved', implode("\n", $this->resolved)),
        ]));
    }
}
