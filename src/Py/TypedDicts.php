<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Node;

/**
 * The classes a codebase declares as `TypedDict`s — known by name.
 */
final class TypedDicts
{
    /**
     * @var array<string, true>
     */
    private array $names = [];

    public function __construct(Codebase $codebase)
    {
        foreach ($codebase->modules() as $module) {
            foreach (array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof ClassDef) as $class) {
                if (array_any($class->bases, static fn (Expr $base): bool => in_array($base->dottedName(), ['TypedDict', 'typing.TypedDict', 'typing_extensions.TypedDict'], true))) {
                    $this->names[$class->name] = true;
                }
            }
        }
    }

    public function isTypedDict(string $name): bool
    {
        return isset($this->names[$name]);
    }
}
