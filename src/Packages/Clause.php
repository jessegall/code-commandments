<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Holds exemption rules for a tag—whole classes, class methods, global methods—answering
 * whether a finding is covered by extends or implements.
 */
final class Clause
{
    /** @var list<class-string> */
    private array $classes = [];

    /** @var array<class-string, list<string>> */
    private array $classMethods = [];

    /** @var list<string> */
    private array $methods = [];

    /**
     * Exempt whole classes — a class extending/implementing any of these is exempt for every method.
     *
     * @param  class-string  ...$classes
     */
    public function classes(string ...$classes): self
    {
        $this->classes = [...$this->classes, ...$classes];

        return $this;
    }

    /**
     * Exempt a base class — the whole class when no methods are named, or just the named methods on
     * its subclasses (a framework contract hook like `rules`).
     *
     * @param  class-string  $class
     */
    public function on(string $class, string ...$methods): self
    {
        if ($methods === []) {
            $this->classes[] = $class;

            return $this;
        }

        $this->classMethods[$class] = [...($this->classMethods[$class] ?? []), ...$methods];

        return $this;
    }

    /**
     * Exempt method NAMES globally — ignored on any class.
     */
    public function methods(string ...$methods): self
    {
        $this->methods = [...$this->methods, ...$methods];

        return $this;
    }

    /**
     * Does this clause exempt ($class, $method)? A null $method asks only the class-level rules; a
     * null $class only the global methods.
     */
    public function matches(Codebase $codebase, ?string $class, ?string $method = null): bool
    {
        if ($class !== null && $this->isA($codebase, $class, $this->classes)) {
            return true;
        }

        if ($method === null) {
            return false;
        }

        if (in_array($method, $this->methods, true)) {
            return true;
        }

        foreach ($this->classMethods as $base => $methods) {
            if (in_array($method, $methods, true) && $this->isA($codebase, (string) $class, [$base])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<class-string>  $bases
     */
    private function isA(Codebase $codebase, string $class, array $bases): bool
    {
        return array_any($bases, static fn (string $base): bool => $codebase->isA($class, $base));
    }
}
