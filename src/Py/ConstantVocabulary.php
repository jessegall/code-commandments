<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\PhpTypes\Option;

/**
 * Which parameters — a function the call index resolves, and one of its parameters — the codebase fills
 * with a named class constant, and from which classes. Indexing slots rather than values is what makes a
 * raw string decidable: a literal equal to some constant proves nothing, but a slot filled with
 * `Token.COLON` in one place and `"{"` in another, while `Token.BRACE_OPEN` holds `"{"`, is the codebase
 * contradicting itself. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\ConstantVocabulary}.
 */
final class ConstantVocabulary
{
    /**
     * @var array<string, list<ClassDef>>|null  slot => the classes whose constants fill it
     */
    private ?array $vocabularies = null;

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * The constant that already names the string $literal, handed to $call — `Token.BRACE_OPEN` — none when
     * the slot it fills is never spelled by name, which is the answer for almost every string.
     *
     * @return Option<string>
     */
    public function nameFor(ExprMatch $call, Expr $literal): Option
    {
        $this->vocabularies ??= $this->vocabularies();

        foreach ($this->slotsFilled($call) as $slot => $argument) {
            if ($argument !== $literal) {
                continue;
            }

            foreach ($this->vocabularies[$slot] ?? [] as $class) {
                $name = $class->stringConstants()[(string) $literal->get('value')] ?? null;

                if ($name !== null) {
                    return Option::some("{$class->name}.{$name}");
                }
            }
        }

        return Option::none();
    }

    /**
     * Every slot some call fills with `Class.CONSTANT`, mapped to the classes those constants belong to.
     *
     * @return array<string, list<ClassDef>>
     */
    private function vocabularies(): array
    {
        $vocabularies = [];

        foreach ($this->codebase->whereCall()->get() as $call) {
            foreach ($this->slotsFilled($call) as $slot => $argument) {
                $class = $argument->is(ExprKind::Attribute) && $argument->get('object')->is(ExprKind::Name)
                    ? $this->codebase->classNamed((string) $argument->get('object')->get('name'))->filter(static fn (ClassDef $owner): bool => $owner->stringConstants() !== [])
                    : Option::none();

                $class->inspect(static function (ClassDef $owner) use (&$vocabularies, $slot): void {
                    if (! in_array($owner, $vocabularies[$slot] ?? [], true)) {
                        $vocabularies[$slot][] = $owner;
                    }
                });
            }
        }

        return $vocabularies;
    }

    /**
     * What $call hands each parameter of the function it reaches, keyed `declaration#parameter`.
     *
     * @return array<string, Expr>
     */
    private function slotsFilled(ExprMatch $call): array
    {
        $index = $this->codebase->index();

        return $index->targetOf($call->expr)->mapOr([], static fn ($target): array => $index->argumentsAt($call->expr)->mapOr([], static fn (array $bound): array => array_combine(
            array_map(static fn (string $name): string => $index->declarationOf($target) . '#' . $name, array_keys($bound)),
            array_values($bound),
        )));
    }
}
