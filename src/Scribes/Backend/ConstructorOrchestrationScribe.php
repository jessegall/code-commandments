<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * Repents page-object slot fills into computed property hooks: `$this->x = expr;` becomes a virtual `get` property.
 * Applies only to single-line assignments to declared properties; multi-line assignments are deferred. The created
 * hook is stamped `#[Computed]` (a get-only virtual property Spatie must NOT treat as a hydration input) and any
 * attributes the property already carried are kept above it, where reflection reads them.
 */
final class ConstructorOrchestrationScribe extends RepentScribe
{
    use ManagesComputedAttribute;

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch && $finding->node instanceof Assign) {
                $this->hoist($draft, $finding, $finding->node);
            }
        }

        return $draft->rewrites();
    }

    private function hoist(Draft $draft, NodeMatch $match, Assign $assign): void
    {
        $class = $match->enclosingClass();
        $statement = $assign->getAttribute('parent');
        $name = $match->assignedPropertyName();

        if (! $class instanceof ClassLike || ! $statement instanceof Node || $name === null) {
            return;
        }

        $property = $this->declaredProperty($class, $name);

        // A single-line assignment onto a single, typed declared property is the only clean case.
        if ($property === null || $property->type === null || $assign->getStartLine() !== $assign->getEndLine()) {
            return;
        }

        $source = $match->file->source;
        $path = $match->file->path;

        $rhs = $this->slice($source, $assign->expr->getStartFilePos(), $assign->expr->getEndFilePos());

        // 1. Stamp `#[Computed]` above the property (a get-only hook is NOT a hydration input) and drop
        //    `readonly` (a virtual property has no backing store to freeze) — keeping any existing
        //    attributes, which sit above and are left untouched, so reflection still reads them.
        $typeStart = $property->type->getStartFilePos();
        $modifiersStart = $property->attrGroups === []
            ? $property->getStartFilePos()
            : end($property->attrGroups)->getEndFilePos() + 1;

        $keywordStart = $modifiersStart;

        while ($keywordStart < $typeStart && ctype_space($source[$keywordStart])) {
            $keywordStart++; // skip the whitespace/newline that follows the last attribute
        }

        $lead = substr($source, $modifiersStart, $keywordStart - $modifiersStart);
        $modifiers = substr($source, $keywordStart, $typeStart - $keywordStart);
        $indent = $this->indentAt($source, $property->getStartFilePos());

        $draft->edit(
            new Span($path, $source, $modifiersStart, $typeStart),
            $lead . "#[Computed]\n{$indent}" . str_replace('readonly ', '', $modifiers),
        );

        // 2. Turn the trailing `;` into the get hook.
        $draft->edit(
            new Span($path, $source, $property->getEndFilePos(), $property->getEndFilePos() + 1),
            " { get => {$rhs}; }",
        );

        // 3. Delete the constructor assignment, whole line.
        [$start, $end] = $this->lineSpan($source, $statement);
        $draft->edit(new Span($path, $source, $start, $end), '');

        // 4. Ensure `use Spatie\LaravelData\Attributes\Computed;` so the stamp resolves.
        $this->ensureComputedImport($draft, $match, $class);
    }

    private function declaredProperty(ClassLike $class, string $name): ?Property
    {
        foreach ($class->getProperties() as $property) {
            if (count($property->props) !== 1) {
                continue; // a grouped `public $a, $b;` can't be split safely
            }

            if ($property->props[0]->name->toString() === $name) {
                return $property;
            }
        }

        return null;
    }

    /**
     * The byte range of the whole line(s) a statement occupies — its leading indentation through the
     * trailing newline — so deleting it leaves no blank line behind.
     *
     * @return array{int, int}
     */
    private function lineSpan(string $source, Node $statement): array
    {
        $newlineBefore = strrpos(substr($source, 0, $statement->getStartFilePos()), "\n");
        $start = $newlineBefore === false ? 0 : $newlineBefore + 1;

        $end = $statement->getEndFilePos() + 1;

        if (($source[$end] ?? '') === "\n") {
            $end++;
        }

        return [$start, $end];
    }

    private function slice(string $source, int $start, int $endInclusive): string
    {
        return substr($source, $start, $endInclusive + 1 - $start);
    }
}
