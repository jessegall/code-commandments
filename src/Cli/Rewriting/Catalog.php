<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Rewriting;

use JesseGall\CodeCommandments\Cli\Hints\DataHintScribe;

/**
 * The roll of Scribes the `scribe` command runs. (An explicit list, not a glob —
 * the order is the apply order, and a Scribe lives wherever its concern does.)
 */
final class Catalog
{
    /**
     * @return list<Scribe>
     */
    public static function all(): array
    {
        return [
            new DataHintScribe,
            new RedundantReturnTypeScribe,
        ];
    }
}
