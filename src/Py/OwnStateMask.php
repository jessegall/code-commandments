<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\Node\ClassDef;

/**
 * A fake answer for the object's own scratch state — `self.period.includes(day) if self.period else False`,
 * `getattr(self.period, "label", "none")` — where the field is optional only because an operation sets it
 * part-way. At the read it should be there; the literal answers a state that can only be a bug. The Python
 * twin of the backend's {@see \JesseGall\CodeCommandments\Ast\Support\OwnStateMask}.
 */
final readonly class OwnStateMask
{
    /**
     * Does $expression, written in $module, answer an absent scratch field of its own class with a literal?
     */
    public function masksOwnState(Expr $expression, ModuleFile $module): bool
    {
        $field = $this->maskedField($expression);

        return $field !== '' && $module->classOf($expression)->isSomeAnd(static fn (ClassDef $class): bool => $class->setsOutsideInit($field)
            && $class->attributeAnnotation($field)->isSomeAnd(static fn (Expr $type): bool => $type->optionalOf()->isSome()));
    }

    /**
     * The own field $expression reaches into with a literal standing in when it is missing — empty when it is
     * no such mask.
     */
    private function maskedField(Expr $expression): string
    {
        if ($expression->isCall() && $expression->get('callee')->dottedName() === 'getattr') {
            $arguments = $expression->get('arguments');

            return count($arguments) === 3 && self::isFakeAnswer($arguments[2]) ? $arguments[0]->selfAttribute() : '';
        }

        if (! $expression->is(ExprKind::Conditional) || ! self::isFakeAnswer($expression->get('else'))) {
            return '';
        }

        $test = $expression->get('test');
        $field = $test->noneTestedOperand()->mapOr($test, static fn (Expr $operand): Expr => $operand)->selfAttribute();
        $reached = array_any($expression->get('then')->flatten(), static fn (Expr $part): bool => $part->is(ExprKind::Attribute) && $part->get('object')->selfAttribute() === $field);

        return $field !== '' && $reached ? $field : '';
    }

    /**
     * A literal other than `None` — the made-up answer, not an honest absence.
     */
    private static function isFakeAnswer(Expr $value): bool
    {
        return $value->is(ExprKind::Literal) && $value->literalType() !== LiteralType::None;
    }
}
