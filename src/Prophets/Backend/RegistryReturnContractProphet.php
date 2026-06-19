<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Enforce the registry contract on a class that opts in via a marker (an
 * interface named `Registry`, or a `#[Registry]` attribute): a registry returns
 * the requested item or THROWS — it does not hand back `Option<T>` or `T | null`.
 *
 * A registry is a TOTAL lookup over a known keyspace; asking for an unregistered
 * key is a programming error, not an expected branch every caller must unwrap.
 * Presence and retrieval split into `has(key): bool` and `get(key): T`. (Same
 * shape as PSR-11 `has()`/`get()` and Laravel `bound()`/`make()`.) The author
 * asserted "this is a registry" with the marker, so the contract is unambiguous.
 *
 * Tier 1 only (marker-driven); the un-marked "looks like a registry" heuristic is
 * intentionally not implemented — that is where false positives breed.
 */
#[IntroducedIn('1.125.0')]
class RegistryReturnContractProphet extends PhpCommandment implements SinRepenter
{
    private const DEFAULT_MARKERS = ['Registry'];

    private const DEFAULT_OPTION_CLASSES = ['Option'];

    /** Getter names that ANNOUNCE nullability is normal — left even on a registry. */
    private const FINDER_PREFIXES = ['find', 'search', 'try', 'lookup'];

    public function description(): string
    {
        return 'A registry returns the item or throws — not Option<T> or T | null (with a has() companion)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A PUBLIC getter on a class marked as a `Registry` (a `Registry` interface or `#[Registry]` attribute) returns `Option<T>` or `T | null`. A registry is a total lookup — a miss is a wiring bug, so it should return T or throw, with a `has()` companion.')
            ->leaveWhen('the method NAME announces nullability is normal — `find*`, `search*`, `try*`, `lookup*`, `*OrNull`, `*OrDefault` — those are genuine value-or-nothing lookups, not the registry contract.')
            ->whenUnsure('if a miss means "you asked for something that was never registered" (a bug), return T and throw; if a miss is an expected, branched-on outcome (a cache, a finder), it is not a registry getter — rename it or drop the marker.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A registry is a TOTAL lookup over a known keyspace — "give me the pipeline for
this class". The expected case is "it's there"; a miss means you asked for a key
that was never registered, which is a programming error. Modelling that as
`Option<T>` / `T | null` pushes a null-check onto every caller for a situation
that is almost always a bug, and scatters `->getOrThrow()` / `?? throw` ceremony
around a throw the registry should own.

Bad — the registry leaks an Option:
    public function pipeline(string $class): Option
    {
        return $this->pipelines[$class] ??= $this->reflect($class);
    }
    // every caller: ->getOrThrow() or if (->isEmpty()) throw …

Good — return-or-throw, with a has() companion:
    public function hasPipeline(string $class): bool
    {
        return $this->resolve($class)->hasValue();
    }
    public function pipeline(string $class): PipelineSpec
    {
        return $this->resolve($class)->getOrThrow();
    }

The internal `Option` memo stays — it just stops leaking across the public
boundary, so callers read "is it there? then get it" and the throw lives where
the keyspace knowledge does.

WHAT FIRES — a PUBLIC method on a class carrying the `Registry` marker (an
interface named `Registry`, or `#[Registry]`) whose return type is `Option<T>`,
`?T`, or `T | null`.

WHAT DOES NOT — a finder-named getter (`find*`/`search*`/`try*`/`lookup*`/
`*OrNull`/`*OrDefault`: a `findByEmail(): ?User` is supposed to miss), a
non-public method, a `bool` `has()`/`is()`, or an `Option` used only INTERNALLY
(a private memo field). The marker is the opt-in, so there is no "is this really
a registry" guessing.

[AUTO-FIXABLE] for a single-return getter: `repent` retypes it to T and wraps the
return (`->getOrThrow()` for an Option, `?? throw` for a nullable). Add the
`has()` companion by hand.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $sins = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->isRegistry($class)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $kind = $this->leakyGetter($method);

                if ($kind === null) {
                    continue;
                }

                $name = $method->name->toString();
                $sins[] = $this->sinAt(
                    $method->getStartLine(),
                    sprintf('Registry getter %s() returns %s — a registry returns the item or throws. Retype it to T (throw on a miss) and add a `has%s()` companion.', $name, $kind === 'option' ? 'an Option' : 'a nullable', ucfirst($name)),
                    $this->lineAt($content, $method->getStartLine()),
                    null,
                    'registry-return:' . $name,
                    $this->isAutoFixable($method, $kind),
                );
            }
        }

        return $sins === [] ? $this->righteous() : $this->fallen($sins);
    }

    private function isRegistry(Node\Stmt\Class_ $class): bool
    {
        $markers = $this->markers();

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (in_array($attr->name->getLast(), $markers, true)) {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            if (in_array($interface->getLast(), $markers, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 'option' / 'nullable' when $method is a public getter leaking absence, else
     * null (finder names, non-public, and non-leaky returns are exempt).
     */
    private function leakyGetter(Node\Stmt\ClassMethod $method): ?string
    {
        if (! $method->isPublic() || $method->isStatic()) {
            return null;
        }

        $name = $method->name->toString();

        if ($this->isFinderName($name) || str_starts_with($name, '__')) {
            return null;
        }

        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return 'nullable';
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return 'nullable';
                }
            }

            return null;
        }

        if ($type instanceof Node\Name && in_array($type->getLast(), $this->optionClasses(), true)) {
            return 'option';
        }

        return null;
    }

    private function isFinderName(string $name): bool
    {
        $lower = strtolower($name);

        foreach (self::FINDER_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return str_ends_with($lower, 'ornull') || str_ends_with($lower, 'ordefault');
    }

    /**
     * Auto-fixable only for a single-return getter whose target type T is known:
     * a nullable (T is the non-null native type) or an `Option<T>` with a
     * `@return Option<T>` docblock.
     */
    private function isAutoFixable(Node\Stmt\ClassMethod $method, string $kind): bool
    {
        if ($this->singleReturn($method) === null) {
            return false;
        }

        return $kind === 'nullable'
            ? $this->nonNullNativeType($method->returnType) !== null
            : $this->optionInnerType($method) !== null;
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $exception = (string) $this->config('miss_exception', '\\RuntimeException');
        $unwrap = (string) $this->config('unwrap_method', 'getOrThrow');
        $edits = [];
        $penance = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->isRegistry($class)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $kind = $this->leakyGetter($method);

                if ($kind === null || ! $this->isAutoFixable($method, $kind)) {
                    continue;
                }

                $return = $this->singleReturn($method);
                $type = $method->returnType;

                if ($return === null || $return->expr === null || $type === null) {
                    continue;
                }

                $target = $kind === 'nullable' ? $this->nonNullNativeType($type) : $this->optionInnerType($method);

                // Retype to T.
                $edits[] = ['start' => (int) $type->getStartFilePos(), 'end' => (int) $type->getEndFilePos(), 'text' => (string) $target];

                // Wrap the returned expression. A trailing `?? null` is stripped
                // so we get `$x[$k] ?? throw`, not `$x[$k] ?? null ?? throw`.
                $expr = $return->expr;

                if ($kind === 'nullable'
                    && $expr instanceof Node\Expr\BinaryOp\Coalesce
                    && $expr->right instanceof Node\Expr\ConstFetch
                    && strtolower($expr->right->name->toString()) === 'null'
                ) {
                    $expr = $expr->left;
                }

                $exprSrc = substr($content, (int) $expr->getStartFilePos(), (int) $expr->getEndFilePos() - (int) $expr->getStartFilePos() + 1);
                $wrapped = $kind === 'option'
                    ? sprintf('(%s)->%s()', $exprSrc, $unwrap)
                    : sprintf('%s ?? throw new %s(%s)', $exprSrc, $exception, var_export(sprintf('%s: no entry for the requested key', $method->name->toString()), true));

                $edits[] = ['start' => (int) $return->expr->getStartFilePos(), 'end' => (int) $return->expr->getEndFilePos(), 'text' => $wrapped];

                $penance[] = sprintf('Retyped %s() to %s and made it return-or-throw (add a has%s() companion by hand)', $method->name->toString(), $target, ucfirst($method->name->toString()));
            }
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    private function singleReturn(Node\Stmt\ClassMethod $method): ?Node\Stmt\Return_
    {
        if ($method->stmts === null) {
            return null;
        }

        $returns = (new NodeFinder)->findInstanceOf($method->stmts, Node\Stmt\Return_::class);

        return count($returns) === 1 ? $returns[0] : null;
    }

    private function nonNullNativeType(?Node $type): ?string
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        } elseif ($type instanceof Node\UnionType) {
            $members = array_values(array_filter($type->types, static fn (Node $m): bool => ! ($m instanceof Node\Identifier && strtolower($m->toString()) === 'null')));

            if (count($members) !== 1) {
                return null;
            }

            $type = $members[0];
        } else {
            return null;
        }

        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        return $type instanceof Node\Name ? $type->toString() : null;
    }

    private function optionInnerType(Node\Stmt\ClassMethod $method): ?string
    {
        $doc = $method->getDocComment();

        if ($doc === null) {
            return null;
        }

        return preg_match('/@return\s+[\\\\\w]+<\s*([\\\\\w]+)\s*>/', $doc->getText(), $m) === 1 ? $m[1] : null;
    }

    /**
     * @return list<string>
     */
    private function markers(): array
    {
        $markers = $this->config('markers', self::DEFAULT_MARKERS);

        return is_array($markers) && $markers !== [] ? array_values(array_map(static fn ($m): string => self::shortName((string) $m), $markers)) : self::DEFAULT_MARKERS;
    }

    /**
     * @return list<string>
     */
    private function optionClasses(): array
    {
        $classes = $this->config('option_classes', self::DEFAULT_OPTION_CLASSES);

        return is_array($classes) && $classes !== [] ? array_values(array_map(static fn ($c): string => self::shortName((string) $c), $classes)) : self::DEFAULT_OPTION_CLASSES;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
