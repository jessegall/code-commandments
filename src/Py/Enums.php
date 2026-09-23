<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Node;

/**
 * The classes a codebase declares as enums — a subclass of `Enum`, `StrEnum`, `IntEnum`, `Flag` or
 * `IntFlag`, directly or through another enum it declares, or a class a decorator builds into one from
 * such a base — known by name.
 */
final class Enums
{
    /**
     * The standard library's enum base classes, as a base list spells them.
     */
    private const array ROOTS = ['Enum', 'StrEnum', 'IntEnum', 'Flag', 'IntFlag', 'ReprEnum'];

    /**
     * @var array<string, true>
     */
    private array $names = [];

    /**
     * @var array<string, list<string>>  each enum's member values, as literal keys
     */
    private array $values = [];

    public function __construct(Codebase $codebase)
    {
        $classes = array_merge([], ...array_map(
            static fn (ModuleFile $module): array => array_values(array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof ClassDef)),
            $codebase->modules(),
        ));

        do {
            $known = count($this->names);

            foreach ($classes as $class) {
                if (array_any([...$class->bases, ...self::decoratorArguments($class)], fn (Expr $base) => $this->isBaseAnEnum($base))) {
                    $this->names[$class->name] = true;
                }
            }
        } while (count($this->names) !== $known);

        foreach ($classes as $class) {
            if ($this->isEnum($class->name)) {
                $this->values[$class->name] = $class->memberValueKeys();
            }
        }
    }

    public function isEnum(string $name): bool
    {
        return isset($this->names[$name]);
    }

    /**
     * Do all of $keys — literal keys — name members of one enum?
     *
     * @param  list<string>  $keys
     */
    public function holdAll(array $keys): bool
    {
        return $keys !== [] && array_any($this->values, static fn (array $values): bool => array_diff($keys, $values) === []);
    }

    /**
     * What $class's decorators are called with — `IntEnum` in `@_simple_enum(IntEnum)`, a decorator that
     * builds an enum out of a plain class body.
     *
     * @return list<Expr>
     */
    private static function decoratorArguments(ClassDef $class): array
    {
        return array_merge([], ...array_map(static fn (Expr $decorator): array => $decorator->isCall() ? $decorator->get('arguments') : [], $class->decorators));
    }

    private function isBaseAnEnum(Expr $base): bool
    {
        $named = substr((string) strrchr('.' . $base->dottedName(), '.'), 1);

        return in_array($named, self::ROOTS, true) || isset($this->names[$named]);
    }
}
