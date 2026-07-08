<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * Stamps `#[Computed]` on a get-only property hook that lacks it (a virtual property Spatie must not treat
 * as a hydration input), placing it beneath any existing attributes and adding the `use` import.
 */
final class HookMissingComputedScribe extends RepentScribe
{
    use ManagesComputedAttribute;

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && $match->node instanceof PropertyHook) {
                $this->stamp($draft, $match);
            }
        }

        return $draft->rewrites();
    }

    private function stamp(Draft $draft, NodeMatch $match): void
    {
        $property = $match->node->getAttribute('parent');
        $class = $match->enclosingClass();

        if (! $property instanceof Property || ! $class instanceof ClassLike) {
            return;
        }

        $source = $match->file->source;
        $indent = $this->indentAt($source, $property->getStartFilePos());

        // Land right before the modifier keyword, after any existing attribute groups.
        $insertAt = $property->attrGroups === []
            ? $property->getStartFilePos()
            : end($property->attrGroups)->getEndFilePos() + 1;

        $keywordStart = $insertAt;

        while ($keywordStart < strlen($source) && ctype_space($source[$keywordStart])) {
            $keywordStart++;
        }

        $lead = substr($source, $insertAt, $keywordStart - $insertAt);

        $draft->edit(
            new Span($match->file->path, $source, $insertAt, $keywordStart),
            $lead . "#[Computed]\n{$indent}",
        );

        $this->ensureComputedImport($draft, $match, $class);
    }
}
