<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\PhpTypes\Option;

/**
 * One PHP file a project wrote into `.commandments/custom/`, and whether requiring it would take the
 * process down with it. PHP decides both of these at class-load and reports them as a FATAL, which no
 * `try` can catch — so a file that would fatal must be recognised before it is loaded, never after. Every
 * hook in the suite runs in one process, so one such file otherwise kills all of them at once, and
 * presents as non-blocking noise while no rule fires at all.
 */
final readonly class CustomFile
{
    /**
     * @param  array<string, NodeMatch>  $declarations  the class-likes this file declares, keyed by FQCN
     */
    private function __construct(
        public string $path,
        private array $declarations,
    ) {}

    /**
     * @param  array<string, NodeMatch>  $declarations  every class-like of the custom folder, keyed by FQCN
     */
    public static function at(string $path, array $declarations): self
    {
        $own = [];

        foreach ($declarations as $fqcn => $declaration) {
            if ($declaration->file() === $path) {
                $own[$fqcn] = $declaration;
            }
        }

        return new self($path, $own);
    }

    /**
     * Why requiring this file would be fatal, or nothing when it is safe to load.
     *
     * @return Option<string>
     */
    public function fault(): Option
    {
        foreach ($this->declarations as $fqcn => $declaration) {
            $fault = $this->faultOf($fqcn, $declaration);

            if ($fault->isSome()) {
                return $fault;
            }
        }

        return Option::none();
    }

    /**
     * @return Option<string>
     */
    private function faultOf(string $fqcn, NodeMatch $declaration): Option
    {
        $elsewhere = $this->declaredElsewhere($fqcn);

        if ($elsewhere->isSome()) {
            return $elsewhere;
        }

        return $this->overrideFault($declaration, $fqcn);
    }

    /**
     * Is $fqcn already declared by a DIFFERENT file? That is the fault worth naming: a worktree checks out
     * its own copy of the custom folder, so one process reaches the same class down two paths, and
     * `require_once` keys on the path rather than the name.
     *
     * Being already declared by the SAME file is not a fault at all — `Custom::load()` runs several times
     * in one process (the config, the agent catalog, the hook registry each ask for it), and `require_once`
     * makes every later pass a no-op. Reporting that would bury the case that matters under one line per
     * file per load, which is the very failure the guard exists to end.
     *
     * @return Option<string>
     */
    private function declaredElsewhere(string $fqcn): Option
    {
        if (! $this->isAlreadyDeclared($fqcn)) {
            return Option::none();
        }

        $declaring = new \ReflectionClass($fqcn)->getFileName();

        if ($declaring === false || $declaring === realpath($this->path)) {
            return Option::none();
        }

        return Option::some("{$fqcn} is already declared by {$declaring} — this file is a second copy of it");
    }

    /**
     * A method declared more strictly than the one it overrides. The parent is the package's own class,
     * which the autoloader can reach, so its real signature is available without touching this file.
     *
     * @return Option<string>
     */
    private function overrideFault(NodeMatch $declaration, string $fqcn): Option
    {
        $parent = $declaration->parentClassName();

        if ($parent === null || ! class_exists($parent)) {
            return Option::none();
        }

        $inherited = $this->visibilitiesOf($parent);

        foreach ($declaration->methodVisibilities() as $method => $visibility) {
            if (isset($inherited[$method]) && AstNode::isStricterVisibility($visibility, $inherited[$method])) {
                return Option::some(
                    "{$fqcn}::{$method}() is {$visibility}, but {$parent} declares it {$inherited[$method]} — "
                    . "an override may not be stricter than what it overrides"
                );
            }
        }

        return Option::none();
    }

    /**
     * @return array<string, string>
     */
    private function visibilitiesOf(string $class): array
    {
        $visibilities = [];

        foreach (new \ReflectionClass($class)->getMethods() as $method) {
            $visibilities[$method->getName()] = match (true) {
                $method->isPrivate() => 'private',
                $method->isProtected() => 'protected',
                default => 'public',
            };
        }

        return $visibilities;
    }

    /**
     * Is $fqcn already in this process — WITHOUT autoloading it, which would load the very file being
     * judged and cause the fatal this exists to avoid.
     */
    private function isAlreadyDeclared(string $fqcn): bool
    {
        return class_exists($fqcn, false)
            || interface_exists($fqcn, false)
            || trait_exists($fqcn, false)
            || enum_exists($fqcn, false);
    }
}
