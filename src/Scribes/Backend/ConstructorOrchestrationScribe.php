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
 * Applies only to single-line assignments to declared properties; multi-line assignments are deferred.
 */
final class ConstructorOrchestrationScribe extends RepentScribe
{
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

        // 1. Drop `readonly` — a virtual property has no backing store to freeze.
        $modifiersStart = $property->attrGroups === []
            ? $property->getStartFilePos()
            : end($property->attrGroups)->getEndFilePos() + 1;
        $modifiers = substr($source, $modifiersStart, $property->type->getStartFilePos() - $modifiersStart);
        $draft->edit(
            new Span($path, $source, $modifiersStart, $property->type->getStartFilePos()),
            str_replace('readonly ', '', $modifiers),
        );

        // 2. Turn the trailing `;` into the get hook.
        $draft->edit(
            new Span($path, $source, $property->getEndFilePos(), $property->getEndFilePos() + 1),
            " { get => {$rhs}; }",
        );

        // 3. Delete the constructor assignment, whole line.
        [$start, $end] = $this->lineSpan($source, $statement);
        $draft->edit(new Span($path, $source, $start, $end), '');
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
