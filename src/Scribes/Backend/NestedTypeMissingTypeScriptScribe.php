<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Stamps `#[TypeScript]` on the nested Data/enum class a flagged property points at — the type that would
 * otherwise generate as `undefined`. Resolves the nested class from each finding via the codebase and
 * stamps it in ITS OWN file (cross-file), composing the {@see Writer}; identical stamps on one shared
 * nested type collapse in the {@see \JesseGall\CodeCommandments\Scribes\Draft}.
 */
final class NestedTypeMissingTypeScriptScribe extends RepentScribe implements NeedsCodebase
{
    private const string TYPE_SCRIPT = 'Spatie\\TypeScriptTransformer\\Attributes\\TypeScript';

    private ?Codebase $codebase = null;

    public function withCodebase(Codebase $codebase): void
    {
        $this->codebase = $codebase;
    }

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        if ($this->codebase === null) {
            return $draft->rewrites();
        }

        foreach ($findings as $match) {
            if (! $match instanceof NodeMatch) {
                continue;
            }

            $nested = $this->codebase->wrap($match->node, $match->file, SpatieDataNode::class)->nestedWireTypeFqcn();

            if ($nested === null) {
                continue;
            }

            $class = $this->codebase->declarationMatch($nested);

            if ($class !== null && $class->node instanceof ClassLike) {
                Writer::for($draft, $class)->stampAttribute($class->node, '#[TypeScript]', self::TYPE_SCRIPT);
            }
        }

        return $draft->rewrites();
    }
}
