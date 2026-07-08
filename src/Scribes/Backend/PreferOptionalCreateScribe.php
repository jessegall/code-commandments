<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;

/**
 * Rewrites a runtime `new Optional` / `new Optional(...)` to `Optional::create()`, reusing the class name
 * EXACTLY as written at the call site (short or fully-qualified) so the reference still resolves. Composes
 * the {@see Writer}.
 */
final class PreferOptionalCreateScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && ($class = $match->newClassNode()) !== null) {
                $writer = Writer::for($draft, $match);
                $writer->replace($match->node, $writer->textOf($class) . '::create()');
            }
        }

        return $draft->rewrites();
    }
}
