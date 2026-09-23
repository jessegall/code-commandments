<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\PhantomNullable;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A field typed `X | None` whose every read assumes it is there and none guards — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\PhantomNullableDetector}, decided by the codebase's
 * {@see \JesseGall\CodeCommandments\Py\AttributeFlow}. Conservative: it needs a read that assumes and none
 * that guards, and a field some method sets back to `None` is genuinely absent at times.
 */
final class PhantomNullableDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new PhantomNullable();
    }

    public function find(Codebase $codebase): array
    {
        $findings = [];

        foreach ($codebase->whereClass()->get() as $match) {
            $class = $match->node;

            if (! $class instanceof ClassDef) {
                continue;
            }

            foreach ($class->fieldNames() as $field) {
                if (! $class->attributeAnnotation($field)->isSomeAnd(static fn (Expr $type): bool => $type->optionalOf()->isSome()) || $class->resetsToNone($field)) {
                    continue;
                }

                $verdict = $codebase->attributeFlow()->verdict($class, $field);

                if ($verdict->assume >= 1 && $verdict->guard === 0) {
                    $class->fieldDeclaration($field)->inspect(static function (Node $declaration) use (&$findings, $match): void {
                        $findings[] = new NodeMatch($declaration, $match->module);
                    });
                }
            }
        }

        return $findings;
    }
}
