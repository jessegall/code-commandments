<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * A subclass that `extends Registry` (a keyed-store base) but OVERRIDES the
 * store accessor (`all()`) to read its OWN private store, bypassing the base's
 * `$items` — so the inherited `register()` / `registerMany()` write a store that
 * `all()`/`find()`/`get()` never read. The class "is a Registry" by inheritance
 * but cannot be registered into; the base's whole mechanism is silently
 * neutered (#119). It is really a *discovered catalog* wearing the base.
 *
 * Fix: either register into the base store (drop the `all()` override, or have it
 * `parent::all()` + your additions), OR stop extending the registry base — it is
 * a catalog/map, not a registration registry.
 *
 * Advisory, never a sin — the right shape is a design call.
 */
#[IntroducedIn('2.1.0')]
class RegistryBaseBypassProphet extends PhpCommandment
{
    private const DEFAULT_MARKERS = ['Registry'];

    private const DEFAULT_ACCESSOR = 'all';

    private const DEFAULT_MUTATORS = ['register', 'registerMany'];

    public function description(): string
    {
        return 'A Registry subclass that overrides all() to a private store leaves inherited register() dead';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class `extends` a registry base, declares its OWN keyed/array store, and overrides the base accessor (`all()`) to read that own store WITHOUT `parent::all()` — while NOT overriding the inherited `register()`/`registerMany()`. Those inherited mutators write the base store, which the override never reads, so registration is dead.')
            ->leaveWhen('the override calls `parent::all()` (it still uses the base store), or the subclass also overrides `register()`/`registerMany()` to feed its own store (the contract is consistent again).')
            ->whenUnsure('ask whether anyone is meant to `register()` into this class. If yes, use the base store (don\'t bypass it). If no — it is populated by discovery/build, not registration — it is a `*Catalog`/`*Map`, not a `Registry`; stop extending the registry base.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A registry base's mechanism is one store fed by `register()` and read by
`all()`/`find()`/`get()`. Override `all()` to read a DIFFERENT (private) store and
you sever that mechanism: the inherited `register()`/`registerMany()` still write
the base `$items`, but nothing reads `$items` any more, so calling them is a
silent no-op.

Bypassed — inherited register() is dead:
    class ResourceRegistry extends Registry
    {
        private array|null $resources = null;
        public function all(): array { return $this->resources ??= $this->build(); }
        // inherits register()/registerMany() → write $items, which all() ignores
    }

Honest options:
  - It IS registered into → use the base store: drop the override, or
    `return [...parent::all(), ...$this->extra];`
  - It is NOT registered into (built/discovered) → it is a catalog, not a
    registry: stop extending the registry base and name it `*Catalog`/`*Map`.

WHAT FIRES — a class extending a `Registry` (marker) base that (1) declares its
own private/protected array store, (2) overrides `all()` without calling
`parent::all()`, and (3) does NOT override `register()`/`registerMany()`.

WHAT DOES NOT — an override that calls `parent::all()`; a subclass that also
overrides the mutators to feed its own store; a class that does not extend a
registry base. Advisory — the right shape is a design decision, not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null
                || ! $this->extendsRegistryBase($class)
                || ! $this->hasOwnArrayStore($class)
                || ! $this->overridesAccessorWithoutParent($class)
                || $this->overridesAnyMutator($class)
            ) {
                continue;
            }

            $name = $class->name->toString();
            $line = $class->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('%s extends a registry base but overrides %s() to read its own store, bypassing the base store. The inherited %s() write a store nothing reads — they are DEAD. Either register into the base store (call `parent::%s()` / drop the override), or stop extending the registry base — this is a discovered catalog (`*Catalog`/`*Map`), not a registration registry.', $name, $this->accessor(), implode('()/', $this->mutators()) . '()', $this->accessor()),
                $this->lineAt($content, $line),
                'registry-base-bypass:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function extendsRegistryBase(Node\Stmt\Class_ $class): bool
    {
        return $class->extends instanceof Node\Name
            && in_array($class->extends->getLast(), $this->markers(), true);
    }

    private function hasOwnArrayStore(Node\Stmt\Class_ $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if ($property->isPrivate() || $property->isProtected()) {
                $type = $property->type;

                // `array`, or a nullable `?array` / `array|null`.
                if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'array') {
                    return true;
                }

                if ($type instanceof Node\NullableType
                    && $type->type instanceof Node\Identifier
                    && strtolower($type->type->toString()) === 'array'
                ) {
                    return true;
                }

                if ($type instanceof Node\UnionType) {
                    foreach ($type->types as $member) {
                        if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'array') {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function overridesAccessorWithoutParent(Node\Stmt\Class_ $class): bool
    {
        $accessor = $this->accessor();

        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() !== $accessor || $method->stmts === null) {
                continue;
            }

            // An override that delegates to the base store is fine.
            foreach ((new NodeFinder)->findInstanceOf($method->stmts, Expr\StaticCall::class) as $call) {
                if ($call->class instanceof Node\Name
                    && strtolower($call->class->toString()) === 'parent'
                    && $call->name instanceof Node\Identifier
                    && $call->name->toString() === $accessor
                ) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function overridesAnyMutator(Node\Stmt\Class_ $class): bool
    {
        $mutators = $this->mutators();

        foreach ($class->getMethods() as $method) {
            if (in_array($method->name->toString(), $mutators, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function markers(): array
    {
        $value = $this->config('markers', self::DEFAULT_MARKERS);

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_MARKERS;
    }

    private function accessor(): string
    {
        $value = $this->config('accessor', self::DEFAULT_ACCESSOR);

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_ACCESSOR;
    }

    /**
     * @return list<string>
     */
    private function mutators(): array
    {
        $value = $this->config('mutators', self::DEFAULT_MUTATORS);

        return is_array($value) && $value !== [] ? array_values(array_map('strval', $value)) : self::DEFAULT_MUTATORS;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
