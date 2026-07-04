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
 * Repents {@see \JesseGall\CodeCommandments\Detectors\Backend\Spatie\ConstructorOrchestrationDetector}:
 * a page object's imperative slot fill becomes a computed property hook. For each flagged
 * `$this->x = <expr>;` it deletes the constructor statement and turns the declared slot into a virtual
 * property that projects itself:
 *
 *   public readonly array $topBarCenter;            →  public array $topBarCenter { get => $this->topBar->center(); }
 *   $this->topBarCenter = $this->topBar->center();  →  (removed)
 *
 * A get-only virtual property IS computed in Spatie (excluded from the payload, evaluated on read), so
 * no `#[Computed]` attribute or import is needed. `readonly` is dropped — a virtual property has no
 * backing store to freeze.
 *
 * Only PROVABLY clean rewrites are applied: the slot must be a single declared property this scribe can
 * see, and the assignment must be a single line (a multi-line right-hand side is left for a human — the
 * detector still flags it). Everything the fix needs is read off the finding; no re-scan.
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
