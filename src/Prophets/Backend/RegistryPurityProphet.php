<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Keep a registry a PURE keyed store of ONE target type (#160). The companion to
 * {@see RegistryReturnContractProphet}: that one governs the return SHAPE (no
 * Option across the boundary), this one governs the surface SCOPE — a registry
 * should expose only register/set, keyed `get(): T`/`has()`, and `all()`/`values()`
 * of its target type `T`. When it grows RESOLUTION methods it has become a second
 * engine (a resolver/query service wearing a registry hat).
 *
 * The target type `T` is read from the class's `@extends Registry<T>` (or
 * `@template-extends`) docblock — so the rule only applies to a registry that
 * DECLARES what it stores (the LEAVE-WHEN: no declared target type → nothing to be
 * incoherent with). A public, non-canonical method is then flagged when it:
 *   - returns a CONCRETE type other than `T` (a foreign query — e.g.
 *     `findWiredSourceSocket(...): Option<OutputSocket>` on a `Registry<NodeDescriptor>`);
 *   - or returns `T` but resolves it from a NON-KEY input (an object parameter, not
 *     the scalar key — e.g. `descriptorForNode(WorkflowNode): NodeDescriptor`).
 *
 * Such methods belong on a dedicated `*Resolver`/`*Query` collaborator that USES the
 * registry. ADVISORY (a WARNING) — surface scope is a judgment call; it never blocks.
 */
#[IntroducedIn('2.13.0')]
class RegistryPurityProphet extends PhpCommandment
{
    /** The pure keyed-store / collection surface — always allowed on a registry. */
    private const CANONICAL = [
        'get', 'set', 'has', 'register', 'registermany', 'unregister', 'remove',
        'forget', 'flush', 'clear', 'all', 'values', 'keys', 'count', 'map',
        'each', 'filter', 'getiterator', 'first', 'find', 'toarray', 'isempty',
        'isnotempty', 'contains',
    ];

    private const SCALAR_OR_PSEUDO = [
        'bool', 'int', 'float', 'string', 'void', 'never', 'array', 'iterable',
        'self', 'static', 'mixed', 'object', 'null', 'true', 'false',
    ];

    public function description(): string
    {
        return 'A registry stays a pure keyed store of its target type — resolution/query methods belong on a collaborator';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A class declaring a registry target type (`@extends Registry<T>`) exposes '
                . 'a public, non-canonical method that RESOLVES rather than looks up: it '
                . 'returns a concrete type other than `T`, or returns `T` from a NON-key '
                . '(object) input. The registry has grown a second engine.'
            )
            ->leaveWhen(
                'the method is the keyed store surface (register/set/get/has/all/values of '
                . 'the target type, including a dynamic-key `get(): T`), the registry '
                . 'declares no `@extends Registry<T>` target type, or the method returns a '
                . 'scalar/bool/array utility (count, keys, …).'
            )
            ->whenUnsure(
                'move the resolution/query method to a dedicated `*Resolver`/`*Reader`/'
                . '`*Query` collaborator that takes the registry as a dependency; leave the '
                . 'registry with only register/get/has/set of its target type. (The '
                . 'collaborator may then return an Option for genuine misses — it is not a '
                . 'registry.)'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A registry is a SIMPLE keyed store of one type: "register THIS with THAT key, get
it back, ask if it is there". Its target type `T` is what it stores — declared via
`@extends Registry<T>`. When a registry also grows RESOLUTION methods — deriving a
value from something other than its key, or returning a different type entirely —
it has quietly become a resolver/query service wearing a registry hat, with two
reasons to change.

Bad — the registry of NodeDescriptor has grown graph/resolution methods:
    /** @extends Registry<NodeDescriptor> */
    class NodeDescriptorRegistry extends Registry
    {
        public function get(string $key): NodeDescriptor { … }          // OK — keyed store
        public function descriptorForNode(WorkflowNode $n): NodeDescriptor { … }  // resolves by a NON-key input
        public function findWiredSourceSocket(…): Option<OutputSocket> { … }       // returns a FOREIGN type
    }

Good — the registry stays pure; resolution moves to a collaborator:
    class NodeDescriptorResolver
    {
        public function __construct(private NodeDescriptorRegistry $registry) {}
        public function forNode(WorkflowNode $n): NodeDescriptor { … }   // uses the registry
    }

WHAT FIRES — a class with an `@extends Registry<T>` (or `@template-extends`)
target type, exposing a public, non-canonical method that returns a concrete type
≠ `T`, or returns `T` from a non-key (object) parameter.

WHAT DOES NOT — the canonical store surface (register/set/get/has/all/values/keys/
count/map/each/first/find of `T`), a registry with no declared target type, a
method returning a scalar/bool/array utility, or a magic method. Advisory (a
WARNING); not auto-fixable (extracting a collaborator is a structural move).
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
            $target = $this->targetType($class);

            if ($target === null) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $reason = $this->impurity($method, $target);

                if ($reason === null) {
                    continue;
                }

                $name = $method->name->toString();

                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    sprintf(
                        'Registry method %s() %s — its target type is `%s`. A registry should stay a pure keyed store (register/get/has/all/values of `%s`); a resolution/query method like this is a second engine. Move it to a dedicated `*Resolver`/`*Query` collaborator that takes this registry as a dependency, leaving the registry pure.',
                        $name,
                        $reason,
                        $target,
                        $target,
                    ),
                    null,
                    'registry-purity:' . $name,
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /** The registry's target type `T` from `@extends Registry<T>`, else null. */
    private function targetType(Node\Stmt\Class_ $class): ?string
    {
        $doc = $class->getDocComment()?->getText() ?? '';

        // Anchor to a REAL PHPDoc tag — `@extends` must follow a doc marker (`/**`
        // single-line, or a `*` line) with only whitespace between, NOT prose words
        // (a description mentioning "the `@extends Registry<T>` tag" would self-match).
        if (preg_match('/(?:\/\*\*|\*)\s*@(?:template-)?extends\s+\\\\?[A-Za-z0-9_\\\\]*Registry\s*<\s*([A-Za-z0-9_\\\\]+)/', $doc, $m) !== 1) {
            return null;
        }

        return $this->shortName($m[1]);
    }

    /** A phrase describing why $method is impure, or null when it is a pure store method. */
    private function impurity(Node\Stmt\ClassMethod $method, string $target): ?string
    {
        if (! $method->isPublic() || $method->isStatic()) {
            return null;
        }

        $name = $method->name->toString();

        if (str_starts_with($name, '__') || in_array(strtolower($name), self::CANONICAL, true)) {
            return null;
        }

        $returned = $this->returnedClass($method);

        if ($returned === null) {
            return null; // scalar/bool/array/void utility — not a resolution
        }

        if ($returned !== $target) {
            return sprintf('returns a foreign type `%s`, not the registry\'s target', $returned);
        }

        // Returns the target type — impure only if it resolves it from a NON-key
        // (object) input rather than the scalar key.
        return $this->hasObjectParam($method)
            ? 'resolves the target from a non-key (object) input rather than a keyed lookup'
            : null;
    }

    /**
     * The concrete CLASS the method effectively returns (native type, or the inner
     * type of an `Option<X>` / `array<…,X>` / `list<X>` / `?X` @return), or null when
     * it returns a scalar/bool/array-of-scalar/void (not a resolved object).
     */
    private function returnedClass(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Name) {
            $short = strtolower($type->getLast());

            // A concrete class return (not Option/array/scalar) — use it directly.
            if ($short !== 'option' && ! in_array($short, self::SCALAR_OR_PSEUDO, true)) {
                return $type->getLast();
            }

            // Option<X> / array<…> carry the real type in the @return docblock.
            if ($short === 'option' || $short === 'array' || $short === 'iterable') {
                return $this->docblockInnerClass($method);
            }

            return null;
        }

        // Identifier (scalar/array/bool/void/iterable) — look at the docblock for an
        // array<…,X>/list<X>/Option<X> inner class; else it is a scalar utility.
        if ($type instanceof Node\Identifier) {
            $name = strtolower($type->toString());

            return ($name === 'array' || $name === 'iterable') ? $this->docblockInnerClass($method) : null;
        }

        return null;
    }

    /** The innermost class-like type named in the method's `@return` generic, or null. */
    private function docblockInnerClass(Node\Stmt\ClassMethod $method): ?string
    {
        $doc = $method->getDocComment()?->getText() ?? '';

        if (preg_match('/@return\s+(.+)/', $doc, $m) !== 1) {
            return null;
        }

        // `class-string`/`callable-string`/… are STRING pseudo-types, not foreign
        // objects — collapse them so a `classForKey(): class-string` keyed lookup is
        // not misread as returning a class named "class".
        $return = preg_replace('/\b(?:class|callable|interface|enum)-string\b/', 'string', $m[1]) ?? $m[1];

        // The last class-like token (e.g. Option<OutputSocket>, array<string, Foo>, list<Foo>).
        if (preg_match_all('/[A-Za-z_\\\\][A-Za-z0-9_\\\\]*/', $return, $tokens) === false) {
            return null;
        }

        foreach (array_reverse($tokens[0]) as $token) {
            $short = $this->shortName($token);

            if (! in_array(strtolower($short), self::SCALAR_OR_PSEUDO, true)
                && strtolower($short) !== 'option'
                && strtolower($short) !== 'list'
            ) {
                return $short;
            }
        }

        return null;
    }

    /** Whether the method takes a parameter typed as an OBJECT (a class) — a non-key input. */
    private function hasObjectParam(Node\Stmt\ClassMethod $method): bool
    {
        foreach ($method->params as $param) {
            $type = $param->type;

            if ($type instanceof Node\NullableType) {
                $type = $type->type;
            }

            if ($type instanceof Node\Name && ! in_array(strtolower($type->getLast()), self::SCALAR_OR_PSEUDO, true)) {
                return true;
            }
        }

        return false;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
