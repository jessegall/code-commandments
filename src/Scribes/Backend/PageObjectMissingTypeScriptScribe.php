<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node\Stmt\Class_;

/**
 * Stamps `#[TypeScript]` on a page-object `Data` class that lacks it, so the transformer generates the
 * frontend type its `.vue` page binds against. Composes the {@see Writer} — beneath any existing class
 * attributes, with the `use` import.
 */
final class PageObjectMissingTypeScriptScribe extends RepentScribe
{
    private const string TYPE_SCRIPT = 'Spatie\\TypeScriptTransformer\\Attributes\\TypeScript';

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && $match->node instanceof Class_) {
                Writer::for($draft, $match)->stampAttribute($match->node, '#[TypeScript]', self::TYPE_SCRIPT);
            }
        }

        return $draft->rewrites();
    }
}
