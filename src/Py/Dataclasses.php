<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\PhpTypes\Option;

/**
 * The dataclasses a codebase declares, by name — what a call building one can be read against.
 */
final class Dataclasses
{
    /**
     * @var array<string, ClassDef>
     */
    private array $classes = [];

    public function __construct(Codebase $codebase)
    {
        foreach ($codebase->modules() as $module) {
            foreach (array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof ClassDef && $node->isDataclass()) as $class) {
                $this->classes[$class->name] = $class;
            }
        }
    }

    /**
     * @return Option<ClassDef>
     */
    public function named(string $name): Option
    {
        return Option::fromNullable($this->classes[$name] ?? null);
    }
}
