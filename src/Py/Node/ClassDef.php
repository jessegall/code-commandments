<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

/**
 * A `class` with its decorators, bases (keyword arguments such as `metaclass=` among them) and body.
 */
final class ClassDef extends Node
{
    /**
     * @param  list<Expr>  $bases
     * @param  list<Expr>  $decorators
     */
    public function __construct(
        public readonly string $name,
        public readonly array $bases,
        public readonly Block $body,
        public readonly array $decorators = [],
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function isScope(): bool
    {
        return true;
    }

    public function expressions(): array
    {
        return [...$this->decorators, ...$this->bases];
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    /**
     * The literal values the class body gives its names — `PAID = "paid"` — as literal keys.
     *
     * @return list<string>
     */
    public function memberValueKeys(): array
    {
        $members = array_filter($this->body->body, static fn (Node $statement): bool => $statement instanceof Assign && count($statement->targets) === 1);

        return array_values(array_filter(array_map(static fn (Assign $member): string => $member->value->literalKey(), $members)));
    }

    /**
     * Is this class a dataclass — decorated `@dataclass`, bare or called?
     */
    public function isDataclass(): bool
    {
        return array_any($this->decorators, static function (Expr $decorator): bool {
            $named = $decorator->isCall() ? $decorator->get('callee') : $decorator;

            return in_array($named->dottedName(), ['dataclass', 'dataclasses.dataclass'], true);
        });
    }

    /**
     * The fields a caller hands this class when building it — the annotated names of its body, less a
     * `ClassVar` and a `field(init=False)`, which the class keeps for itself.
     *
     * @return list<string>
     */
    public function initFieldNames(): array
    {
        $fields = array_filter($this->body->body, static fn (Node $statement): bool => $statement instanceof AnnAssign
            && $statement->target->is(ExprKind::Name)
            && ! $statement->annotation->isClassVarType()
            && ! self::isKeptOutOfInit($statement->value));

        return array_values(array_map(static fn (AnnAssign $field): string => (string) $field->target->get('name'), $fields));
    }

    /**
     * The fields a caller must hand over as text — annotated plain `str`, with no default.
     *
     * @return list<string>
     */
    public function requiredTextFields(): array
    {
        $fields = array_filter($this->body->body, static fn (Node $statement): bool => $statement instanceof AnnAssign
            && $statement->target->is(ExprKind::Name)
            && $statement->value === null
            && $statement->annotation->dottedName() === 'str');

        return array_values(array_map(static fn (AnnAssign $field): string => (string) $field->target->get('name'), $fields));
    }

    /**
     * The methods written in this class's body, the constructor and its `__post_init__` aside.
     *
     * @return list<FunctionDef>
     */
    public function methodsAfterConstruction(): array
    {
        return array_values(array_filter($this->body->body, static fn (Node $statement): bool => $statement instanceof FunctionDef
            && ! in_array($statement->name, ['__init__', '__post_init__'], true)));
    }

    /**
     * Is $default a `field(…, init=False)` — a field the class fills itself?
     */
    private static function isKeptOutOfInit(?Expr $default): bool
    {
        $arguments = $default?->isCall() === true && $default->get('callee')->dottedName() === 'field' ? $default->get('arguments') : [];

        return array_any($arguments, static fn (Expr $argument): bool => $argument->is(ExprKind::Keyword) && $argument->get('name') === 'init' && $argument->get('value')->get('value') === 'False');
    }

    /**
     * The attributes its methods ask `self` about being absent — `note` in `if self.note is None:`.
     *
     * @return list<string>
     */
    public function attributesTestedForAbsence(): array
    {
        $names = [];

        foreach ($this->body->descendants() as $node) {
            foreach ($node->expressions() as $expression) {
                foreach ($expression->flatten() as $part) {
                    $part->noneTestedOperand()->inspect(static function (Expr $operand) use (&$names): void {
                        $names[] = $operand->selfAttribute();
                    });
                }
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }
}
