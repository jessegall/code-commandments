<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Node;

/**
 * The classes a codebase declares as enums — a subclass of `Enum`, `StrEnum`, `IntEnum`, `Flag` or
 * `IntFlag`, directly or through another enum it declares — known by name.
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

    public function __construct(Codebase $codebase)
    {
        $classes = array_merge([], ...array_map(
            static fn (ModuleFile $module): array => array_values(array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof ClassDef)),
            $codebase->modules(),
        ));

        do {
            $known = count($this->names);

            foreach ($classes as $class) {
                if (array_any($class->bases, fn (Expr $base) => $this->isBaseAnEnum($base))) {
                    $this->names[$class->name] = true;
                }
            }
        } while (count($this->names) !== $known);
    }

    public function isEnum(string $name): bool
    {
        return isset($this->names[$name]);
    }

    private function isBaseAnEnum(Expr $base): bool
    {
        $named = substr((string) strrchr('.' . $base->dottedName(), '.'), 1);

        return in_array($named, self::ROOTS, true) || isset($this->names[$named]);
    }
}
