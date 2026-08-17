<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Text\BlankText;

/**
 * Opens a picking run, optionally pinned to one lane. The lane is a total `string` defaulting to the
 * blank, and the run reads that blank back as "no lane was named" — asked through a predicate rather
 * than spelled `=== ''`, which changes who writes the comparison but not what is being decided.
 */
final class PickingLane
{
    /**
     * @var list<string>
     */
    private array $picked = [];

    #[Sinful(BlankStringDefault::class)]
    public function open(string $run, string $lane = ''): string
    {
        if (BlankText::isNot($lane)) {
            $this->picked[] = $lane;

            return $run . '@' . $lane;
        }

        return $run;
    }

    /**
     * @return list<string>
     */
    public function picked(): array
    {
        return $this->picked;
    }
}
