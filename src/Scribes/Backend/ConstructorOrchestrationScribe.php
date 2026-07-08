<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * Repents page-object slot fills into computed property hooks: `$this->x = expr;` becomes a virtual `get` property.
 * Applies only to single-line assignments to declared properties; multi-line assignments are deferred. The created
 * hook is stamped `#[Computed]` (a get-only virtual property Spatie must NOT treat as a hydration input) and any
 * attributes the property already carried are kept above it, where reflection reads them. All rewriting composes
 * the {@see Writer}.
 */
final class ConstructorOrchestrationScribe extends RepentScribe
{
    private const string COMPUTED = 'Spatie\\LaravelData\\Attributes\\Computed';

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

        $rhs = $this->slice($match->file->source, $assign->expr->getStartFilePos(), $assign->expr->getEndFilePos());
        $writer = Writer::for($draft, $match);

        // 1. Stamp `#[Computed]` above the property (a get-only hook is NOT a hydration input, and the import
        //    rides along) and drop `readonly` (a virtual property has no backing store) — existing attributes
        //    are kept above, where reflection reads them.
        $writer->stampAttribute($property, '#[Computed]', self::COMPUTED);
        $writer->dropModifier($property, 'readonly');

        // 2. Turn the trailing `;` into the get hook, 3. delete the constructor assignment line.
        $writer->rewriteRange($property->getEndFilePos(), $property->getEndFilePos() + 1, " { get => {$rhs}; }");
        $writer->deleteStatementLine($statement);
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

    private function slice(string $source, int $start, int $endInclusive): string
    {
        return substr($source, $start, $endInclusive + 1 - $start);
    }
}
