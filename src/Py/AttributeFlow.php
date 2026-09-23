<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\FlowVerdict;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\PhpTypes\Option;

/**
 * How a class's attributes are read across a codebase — whether the reads ASSUME a value is there (reach
 * into it, call it, index it) or ACKNOWLEDGE it may be missing (test it against `None`, test it bare,
 * negate it, fall back with `or`). A read is `self.x` inside the class, or `obj.x` on any receiver mypy
 * types as the class; a receiver it could not type is never guessed. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\ValueFlow}.
 */
final class AttributeFlow
{
    public function __construct(private readonly Codebase $codebase) {}

    public function verdict(ClassDef $class, string $attribute): FlowVerdict
    {
        $assume = 0;
        $guard = 0;

        foreach ($this->codebase->modules() as $module) {
            foreach ($module->expressions() as $read) {
                if (! $this->reads($read, $module, $class, $attribute)) {
                    continue;
                }

                $around = $module->wrapperOf($read);
                $guard += $this->isGuarded($read, $around, $module) ? 1 : 0;
                $assume += $around->isSomeAnd(static fn (Expr $wrapper): bool => $wrapper->dereferences($read)) ? 1 : 0;
            }
        }

        return new FlowVerdict($assume, $guard);
    }

    /**
     * Is $read the attribute $attribute of $class — `self.x` in one of its methods, or `obj.x` where mypy
     * typed `obj` as the class?
     */
    private function reads(Expr $read, ModuleFile $module, ClassDef $class, string $attribute): bool
    {
        if (! $read->is(ExprKind::Attribute) || $read->get('name') !== $attribute) {
            return false;
        }

        if ($read->selfAttribute() !== '') {
            return $module->classOf($read)->isSomeAnd(static fn (ClassDef $owner): bool => $owner === $class);
        }

        $receiver = $read->get('object');

        return $this->codebase->types()->at($module->file, $receiver->start, $receiver->end)->isSomeAnd(
            static fn (Type $type): bool => $type->className()->isSomeAnd(static fn (string $name): bool => str_ends_with(".{$name}", ".{$class->name}")),
        );
    }

    /**
     * Does the expression around $read — or $read standing as a condition — admit it may be missing?
     *
     * @param  Option<Expr>  $around
     */
    private function isGuarded(Expr $read, Option $around, ModuleFile $module): bool
    {
        return $module->isTested($read) || $around->isSomeAnd(static fn (Expr $wrapper): bool => $wrapper->acknowledgesAbsenceOf($read));
    }
}
