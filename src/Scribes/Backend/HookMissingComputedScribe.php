<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Stmt\Property;

/**
 * Stamps `#[Computed]` on a get-only property hook that lacks it (a virtual property Spatie must not treat
 * as a hydration input). Composes the {@see Writer} — beneath any existing attributes, with the import.
 */
final class HookMissingComputedScribe extends RepentScribe
{
    private const string COMPUTED = 'Spatie\\LaravelData\\Attributes\\Computed';

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && $match->node instanceof PropertyHook && $match->node->getAttribute('parent') instanceof Property) {
                Writer::for($draft, $match)->stampAttribute($match->node->getAttribute('parent'), '#[Computed]', self::COMPUTED);
            }
        }

        return $draft->rewrites();
    }
}
