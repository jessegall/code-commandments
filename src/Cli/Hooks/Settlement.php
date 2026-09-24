<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Cli\Dashboard\SinsDashboard;
use JesseGall\CodeCommandments\Cli\Dashboard\StoredFinding;
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
     * The events the journal shows for it: a sin-found for each sin found, its mark opening the Sins
     * dashboard's page for that rule in that file, and one sin-resolved for the sins repented.
     *
     * @return list<JournalRaise>
     */
    public function raises(string $root): array
    {
        $found = array_map(static fn (SinMark $mark) => new JournalRaise('sin-found', $mark->shownFrom($root), SinsDashboard::opening(StoredFinding::of($mark->finding(), $root))), $this->found);

        return $this->resolved === [] ? $found : [...$found, new JournalRaise('sin-resolved', implode("\n", $this->resolved))];
    }
}
