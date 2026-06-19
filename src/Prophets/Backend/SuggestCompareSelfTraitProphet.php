<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindChainedEnumEqualityComparisons;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\NodeFinder;
use ReflectionEnum;

/**
 * Surface raw enum equality comparisons that should route through the
 * CompareSelf trait's `equals` family — single comparisons and the boolean
 * chains that string them together.
 *
 *   Before:
 *     if (
 *         $descriptor->kind === NodeKind::Trigger
 *         || $descriptor->kind === NodeKind::Input
 *         || $descriptor->kind === NodeKind::Output
 *     ) { … }
 *
 *   After (null-safe static form — never crashes on a null/non-enum LHS):
 *     if (NodeKind::equalsAny($descriptor->kind, NodeKind::Trigger, NodeKind::Input, NodeKind::Output)) { … }
 *
 * The prophet emits two tiers:
 *
 *   - Primary warning when the matched enum already uses the configured
 *     trait — the static rewrite is valid and is [AUTO-FIXABLE].
 *
 *   - Adoption hint (also a warning, but deduplicated per enum per file)
 *     when the enum exists but hasn't adopted the trait yet — a single nudge
 *     to adopt the trait first. NOT auto-fixable: the static call would hit a
 *     non-existent `__callStatic` until the trait is in place.
 *
 * The trait FQCN, the four `equals`-family method names, minimum chain length,
 * and excluded enums are all configurable.
 */
#[IntroducedIn('1.15.0')]
class SuggestCompareSelfTraitProphet extends PhpCommandment implements SinRepenter, NeedsCodebaseIndex
{
    private const DEFAULT_TRAIT = 'App\\Support\\Enums\\CompareSelf';
    private const EQUALS = 'equals';
    private const EQUALS_ANY = 'equalsAny';
    private const NOT_EQUALS = 'notEquals';
    private const NOT_EQUALS_ANY = 'notEqualsAny';

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    /**
     * Whether the configured trait actually defines $method. #57: the trait may
     * use different names (is()/isNot()) than the configured equals()/notEquals(),
     * so emitting the call would fatal at runtime. Verified through the index;
     * when the trait can't be verified (no index, or it lives outside the scroll)
     * we allow the rewrite rather than over-suppress.
     */
    private function traitDefinesMethod(string $traitFqcn, string $method): bool
    {
        if ($this->index === null) {
            return true;
        }

        // The compare-self helper is usually a TRAIT; fall back to a class for
        // class-based setups. Unverifiable (outside the scroll) => allow.
        $methods = $this->index->traitMethodNames($traitFqcn)
            ?? ($this->index->classByFqcn(ltrim($traitFqcn, '\\'))?->methods !== null
                ? array_map('strtolower', array_keys($this->index->classByFqcn(ltrim($traitFqcn, '\\'))->methods))
                : null);

        if ($methods === null) {
            return true;
        }

        return in_array(strtolower($method), $methods, true);
    }

    public function description(): string
    {
        return 'Use a CompareSelf-style trait helper instead of chained enum equality comparisons';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Raw enum equality with `===` / `!==` scatters comparison logic and is not
null-safe — a null or non-enum left-hand side silently fails the test rather
than answering it. The CompareSelf trait names the comparison and makes it
null-safe under ONE family of helpers:

    use CompareSelf;
    // instance: $case->equals($x) / notEquals / equalsAny / notEqualsAny
    // static (null-safe): Enum::equals($value, $case) / notEquals / equalsAny / notEqualsAny

This prophet matches a subject compared to case(s) of one enum — single
comparisons AND the boolean chains that string them together:

  - single `$x === Case`            -> equals
  - single `$x !== Case`            -> notEquals
  - `||` chain of `===`            -> equalsAny
  - `&&` chain of `!==`            -> notEqualsAny

A chain must contain at least `min_chain` atoms (default 1 — single
comparisons are flagged too). Mixed-enum chains, chains with different
left-hand sides, and `match` expressions are intentionally ignored.

The rewrite is always null-safe and reuses the enum class reference exactly
as written, so no new `use` import is needed. A SINGLE comparison against
one known case anchors on the case — the case literal is never null, so
`Case->equals($x)` is just as null-safe as the static helper and reads
better. Only multi-case sets keep the static form (there is no single case
to anchor on):

    $x === Status::A                      ->  Status::A->equals($x)
    $x !== Status::A                      ->  Status::A->notEquals($x)
    $x === Status::A || $x === Status::B  ->  Status::equalsAny($x, Status::A, Status::B)
    $x !== Status::A && $x !== Status::B  ->  Status::notEqualsAny($x, Status::A, Status::B)

Use the STATIC form ONLY when the value being checked against is dynamic
(neither operand is a literal case). When you already know the case, an
existing static call is itself flagged and re-anchored:

    Status::equals($x, Status::A)         ->  Status::A->equals($x)
    Status::notEquals($x, Status::A)      ->  Status::A->notEquals($x)

Severities are tiered:

  - SIN ([AUTO-FIXABLE]) for an existing static `Enum::equals($x, Enum::Case)`
    against a literal case — unambiguously the wrong form (the call already
    uses the trait API; the case must anchor on itself), so `repent` rewrites
    it to `Enum::Case->equals($x)`.

  - WARNING ([AUTO-FIXABLE]) for a raw `===`/`!==` comparison when the matched
    enum already uses the configured trait — the helper exists, so `repent`
    rewrites it.

  - WARNING (quieter, one per enum per file) when the enum exists but
    hasn't adopted the trait yet — a nudge to add the trait first. NOT
    auto-fixed: the static call would hit a missing `__callStatic` until
    the enum `use`s the trait.

Comparisons inside `toArray`, `jsonSerialize`, `render`, or inside a
`JsonResource` / `Resource` / `Response` class are left alone — those
are wire-format boundaries where literal-shaped logic is the contract.

A `=== Enum::Case` (or `!==`) that is a load-bearing NARROWING GUARD — the
sole condition of an `if` that bails (`continue` / `return` / `throw` /
`break`) — is also left alone: PHPStan narrows the enum through `===` but
NOT through the trait's `equals()`, so converting it would break a later
exhaustive `match`.

Configuration:

    SuggestCompareSelfTraitProphet::class => [
        'trait' => App\Support\Enums\CompareSelf::class,
        'equals_method' => 'equals',
        'equals_any_method' => 'equalsAny',
        'not_equals_method' => 'notEquals',
        'not_equals_any_method' => 'notEqualsAny',
        'min_chain' => 1,
        'exclude_enums' => [],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $traitFqcn = $this->traitFqcn();
        $minChain = (int) $this->config('min_chain', 1);
        $pipe = $this->buildPipe($minChain);

        $pipeline = PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe);

        $ast = $pipeline->getContext()->ast ?? [];
        $uses = $this->extractUses($ast);
        $namespace = $this->extractNamespace($ast);
        $seenAdoptionHint = [];

        return $pipeline
            ->partitionMatches(function (MatchResult $match) use ($traitFqcn, $ast, $uses, $namespace, &$seenAdoptionHint): Sin|Warning|null {
                $enumFqcn = $match->groups['enum_fqcn'];

                // `$x === Foo::BAR` is only enum-case equality when Foo is an
                // actual enum. Value classes / node classes with string
                // constants (and bogus `self::` resolutions on non-enums) are
                // plain constant comparisons — never flag them.
                if (! $this->isEnum($enumFqcn, $ast)) {
                    return null;
                }

                // #66: the other operand is the enum's BACKING scalar, not an enum
                // instance — `equals(?Enum)` would TypeError on it. Leave the `===`.
                if ($this->operandIsBackingScalar($match->groups['start'] ?? '', $ast, $uses, $namespace)) {
                    return null;
                }

                // An EXISTING `Enum::equals($x, Enum::Case)` static call already
                // routes through the trait (it would not compile otherwise) and
                // is unambiguously the wrong form — a known case must anchor on
                // itself. That is a SIN (auto-fixable), not a soft nudge.
                if (($match->groups['from_static'] ?? '0') === '1') {
                    return $this->staticAnchorSin($match);
                }

                $hasTrait = $this->enumUsesTrait($enumFqcn, $traitFqcn, $ast);

                if ($hasTrait === true) {
                    return $this->primaryWarning($match, $traitFqcn);
                }

                $key = $enumFqcn;

                if (isset($seenAdoptionHint[$key])) {
                    return null;
                }

                $seenAdoptionHint[$key] = true;

                return $this->adoptionHint($match, $traitFqcn);
            })
            ->judge();
    }

    /**
     * #66: whether the non-enum operand of the comparison rooted at $startPos is
     * a SCALAR (the enum's backing value) rather than an enum instance. The
     * generated `equals(?Enum)` is typed for the enum, so rewriting a string/int
     * operand to it TypeErrors — such comparisons must be left as `===`.
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     */
    private function operandIsBackingScalar(string $startPos, array $ast, array $uses, ?string $namespace): bool
    {
        $finder = new NodeFinder;
        $start = (int) $startPos;
        $cmp = null;

        foreach ($finder->find($ast, static fn (Node $n): bool => $n instanceof Node\Expr\BinaryOp && (int) $n->getStartFilePos() === $start) as $node) {
            $cmp = $node;
            break;
        }

        if ($cmp === null) {
            return false;
        }

        $operand = $this->nonEnumOperand($cmp, $finder);

        if ($operand === null) {
            return false;
        }

        // Provably a scalar / mixed type (param, property, literal, cast)…
        if ($this->scalarTypeOfOperand($operand, $start, $ast, $finder, $uses, $namespace) !== null) {
            return true;
        }

        // …or the SAME operand is compared to a scalar literal in this scope
        // (`$channel === ChannelType::POS || $channel === 'pos'`). You never
        // compare an enum instance to a string/int literal, so the value is the
        // backing scalar — #71 (the Arr::get()-as-mixed case).
        return $this->comparedToScalarLiteralNearby($operand, $start, $ast, $finder);
    }

    private function comparedToScalarLiteralNearby(Node $operand, int $pos, array $ast, NodeFinder $finder): bool
    {
        $fn = $this->enclosingFunctionNode($pos, $ast, $finder);
        $scope = $fn !== null ? [$fn] : $ast;

        foreach ($finder->find($scope, static fn (Node $n): bool =>
            $n instanceof Node\Expr\BinaryOp\Identical
            || $n instanceof Node\Expr\BinaryOp\NotIdentical
            || $n instanceof Node\Expr\BinaryOp\Equal
            || $n instanceof Node\Expr\BinaryOp\NotEqual
        ) as $bin) {
            foreach ([[$bin->left, $bin->right], [$bin->right, $bin->left]] as [$a, $b]) {
                if (($b instanceof Node\Scalar\String_ || $b instanceof Node\Scalar\Int_) && $this->sameOperand($a, $operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sameOperand(Node $a, Node $b): bool
    {
        if ($a instanceof Node\Expr\Variable && $b instanceof Node\Expr\Variable) {
            return $a->name === $b->name;
        }

        if ($a instanceof Node\Expr\PropertyFetch && $b instanceof Node\Expr\PropertyFetch
            && $a->name instanceof Node\Identifier && $b->name instanceof Node\Identifier
            && $a->name->toString() === $b->name->toString()
        ) {
            return $this->sameOperand($a->var, $b->var);
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enclosingFunctionNode(int $pos, array $ast, NodeFinder $finder): ?Node
    {
        $best = null;
        $bestStart = -1;

        $functions = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Node\Expr\Closure::class),
        );

        foreach ($functions as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start <= $pos && (int) $fn->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $fn;
                $bestStart = $start;
            }
        }

        return $best;
    }

    private function nonEnumOperand(Node $cmp, NodeFinder $finder): ?Node
    {
        foreach ($finder->find([$cmp], static fn (Node $n): bool =>
            $n instanceof Node\Expr\BinaryOp\Identical
            || $n instanceof Node\Expr\BinaryOp\NotIdentical
            || $n instanceof Node\Expr\BinaryOp\Equal
            || $n instanceof Node\Expr\BinaryOp\NotEqual
        ) as $bin) {
            $leftEnum = $bin->left instanceof Node\Expr\ClassConstFetch;
            $rightEnum = $bin->right instanceof Node\Expr\ClassConstFetch;

            if ($leftEnum && ! $rightEnum) {
                return $bin->right;
            }

            if ($rightEnum && ! $leftEnum) {
                return $bin->left;
            }
        }

        return null;
    }

    /**
     * The scalar type of $operand ('string'/'int'/…), or null when it is not
     * provably a scalar (an enum instance, an object, or unresolved).
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     */
    private function scalarTypeOfOperand(Node $operand, int $pos, array $ast, NodeFinder $finder, array $uses, ?string $namespace): ?string
    {
        if ($operand instanceof Node\Scalar\String_) {
            return 'string';
        }

        if ($operand instanceof Node\Scalar\Int_) {
            return 'int';
        }

        if ($operand instanceof Node\Expr\Cast\String_) {
            return 'string';
        }

        if ($operand instanceof Node\Expr\Cast\Int_) {
            return 'int';
        }

        if ($operand instanceof Node\Expr\PropertyFetch
            && $operand->var instanceof Node\Expr\Variable
            && $operand->name instanceof Node\Identifier
        ) {
            $prop = $operand->name->toString();

            // `$this->prop` — read the scalar property type from the enclosing class.
            if ($operand->var->name === 'this') {
                $class = $this->enclosingClass($pos, $ast, $finder);

                return $class !== null ? $this->classScalarPropertyType($class, $prop) : null;
            }

            // `$x->prop` — resolve $x's (object) param type, then the property's
            // scalar type via the index.
            if (is_string($operand->var->name)) {
                $ownerType = $this->objectParamFqcn($operand->var->name, $pos, $ast, $finder, $uses, $namespace);
                $summary = $ownerType !== null ? $this->index?->classByFqcn($ownerType) : null;

                return $summary->scalarPropertyTypes[$prop] ?? null;
            }
        }

        if ($operand instanceof Node\Expr\Variable && is_string($operand->name)) {
            $type = $this->scalarParamType($operand->name, $pos, $ast, $finder);

            return $type;
        }

        return null;
    }

    /**
     * The lowercased scalar type of param $name in the innermost function-like
     * containing $pos, or null when it is not a scalar.
     *
     * @param  array<Node>  $ast
     */
    private function scalarParamType(string $name, int $pos, array $ast, NodeFinder $finder): ?string
    {
        $type = $this->paramTypeNode($name, $pos, $ast, $finder);

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            $lower = strtolower($type->toString());

            // `mixed` counts: equals(?Enum) would TypeError on a non-enum value (#71).
            return in_array($lower, ['string', 'int', 'float', 'bool', 'mixed'], true) ? $lower : null;
        }

        return null;
    }

    /**
     * The resolved FQCN of param $name when it is an OBJECT type, else null.
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     */
    private function objectParamFqcn(string $name, int $pos, array $ast, NodeFinder $finder, array $uses, ?string $namespace): ?string
    {
        $type = $this->paramTypeNode($name, $pos, $ast, $finder);

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        return $type instanceof Node\Name ? ltrim(NameResolver::resolve($type->toString(), $uses, $namespace), '\\') : null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function paramTypeNode(string $name, int $pos, array $ast, NodeFinder $finder): ?Node
    {
        $best = null;
        $bestStart = -1;

        $functions = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Node\Expr\Closure::class),
            $finder->findInstanceOf($ast, Node\Expr\ArrowFunction::class),
        );

        foreach ($functions as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start > $pos || (int) $fn->getEndFilePos() < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Node\Expr\Variable && $param->var->name === $name && $param->type !== null) {
                    $best = $param->type;
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enclosingClass(int $pos, array $ast, NodeFinder $finder): ?Node\Stmt\Class_
    {
        $best = null;
        $bestStart = -1;

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $start = (int) $class->getStartFilePos();

            if ($start <= $pos && (int) $class->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $class;
                $bestStart = $start;
            }
        }

        return $best;
    }

    private function classScalarPropertyType(Node\Stmt\Class_ $class, string $property): ?string
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property) {
                    $type = $prop->type instanceof Node\NullableType ? $prop->type->type : $prop->type;

                    return $type instanceof Node\Identifier && in_array(strtolower($type->toString()), ['string', 'int', 'float', 'bool', 'mixed'], true)
                        ? strtolower($type->toString())
                        : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, string>  alias => FQCN
     */
    private function extractUses(array $ast): array
    {
        $uses = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $u) {
                $uses[$u->getAlias()->toString()] = $u->name->toString();
            }
        }

        return $uses;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function extractNamespace(array $ast): ?string
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            return $ns->name?->toString();
        }

        return null;
    }

    private function buildPipe(int $minChain): FindChainedEnumEqualityComparisons
    {
        $excludeEnums = $this->config('exclude_enums', []);

        if (! is_array($excludeEnums)) {
            $excludeEnums = [];
        }

        return (new FindChainedEnumEqualityComparisons)
            ->withMinChain($minChain)
            ->withEqualityMethods(
                (string) $this->config('equals_method', self::EQUALS),
                (string) $this->config('not_equals_method', self::NOT_EQUALS),
            )
            ->withExcludeEnums(array_values(array_map(static fn ($v) => (string) $v, $excludeEnums)));
    }

    private function traitFqcn(): string
    {
        $raw = (string) $this->config('trait', self::DEFAULT_TRAIT);

        return ltrim($raw, '\\');
    }

    /**
     * Map a pipe op to the configured method name.
     */
    private function methodFor(string $op): string
    {
        return match ($op) {
            'equals' => (string) $this->config('equals_method', self::EQUALS),
            'not_equals' => (string) $this->config('not_equals_method', self::NOT_EQUALS),
            'one_of' => (string) $this->config('equals_any_method', self::EQUALS_ANY),
            'not_one_of' => (string) $this->config('not_equals_any_method', self::NOT_EQUALS_ANY),
            default => (string) $this->config('equals_method', self::EQUALS),
        };
    }

    /**
     * Build the null-safe rewrite.
     *
     * A single comparison against ONE known case anchors on the case —
     * `Class::Case->equals($lhs)` — which is null-safe (the case literal is
     * never null) and reads better than the static helper. The static form
     * `Class::method($lhs, Class::CaseA, …)` is reserved for multi-case sets
     * (`equalsAny`/`notEqualsAny`), where there is no single case to anchor on.
     *
     * @param  array<string, string>  $groups
     */
    private function rewrite(array $groups): string
    {
        $classRef = $groups['class_ref'];
        $cases = explode(',', $groups['cases']);
        $op = $groups['op'];
        $method = $this->methodFor($op);

        if (($op === 'equals' || $op === 'not_equals') && count($cases) === 1) {
            return sprintf('%s::%s->%s(%s)', $classRef, $cases[0], $method, $groups['lhs']);
        }

        $args = array_merge(
            [$groups['lhs']],
            array_map(static fn (string $c) => $classRef . '::' . $c, $cases),
        );

        return sprintf('%s::%s(%s)', $classRef, $method, implode(', ', $args));
    }

    private function primaryWarning(MatchResult $match, string $traitFqcn): Warning
    {
        $groups = $match->groups;
        $method = $this->methodFor($groups['op']);
        $defined = $this->traitDefinesMethod($traitFqcn, $method);

        $message = sprintf(
            '%s comparison on %s — use the null-safe `%s`.',
            $this->opLabel($groups['op']),
            $groups['enum_short'],
            $this->rewrite($groups),
        );

        // #57: the configured trait does not define this method (it uses other
        // names) — auto-fixing would emit an undefined-method call. Nudge to
        // configure the *_method names instead of promising a broken rewrite.
        if (! $defined) {
            $message .= sprintf(
                ' (NOT auto-fixable: %s does not define `%s()` — set the `*_method` config to your trait\'s actual method names.)',
                $this->shortName($traitFqcn),
                $method,
            );
        }

        return Warning::at(
            line: $match->line,
            message: $message,
            snippet: $match->content,
            autoFixable: $defined,
        );
    }

    /**
     * A static `Enum::equals($x, Enum::Case)` against a literal case is the
     * wrong form — anchor on the case. This is a sin (auto-fixable), since it
     * is unambiguous and already uses the trait API.
     */
    private function staticAnchorSin(MatchResult $match): Sin
    {
        $groups = $match->groups;
        $case = explode(',', $groups['cases'])[0];
        $rewrite = $this->rewrite($groups);

        $message = sprintf(
            'Static `%s::%s(...)` checks against the known case `%s::%s` — only use the static form when the value being checked against is dynamic. Anchor on the case instead.',
            $groups['enum_short'],
            $this->methodFor($groups['op']),
            $groups['enum_short'],
            $case,
        );

        return Sin::at(
            line: $match->line,
            message: $message,
            snippet: $match->content,
            suggestion: sprintf('Rewrite as `%s` (auto-fixable).', $rewrite),
        );
    }

    private function adoptionHint(MatchResult $match, string $traitFqcn): Warning
    {
        $groups = $match->groups;
        $traitShort = $this->shortName($traitFqcn);

        $message = sprintf(
            '[ADOPT] %s could adopt the `%s` trait — %s comparisons on it would route through the null-safe `equals` API (e.g. line %d). Add `use %s;` to the enum first, then the rewrite applies.',
            $groups['enum_short'],
            $traitShort,
            $this->opLabel($groups['op']),
            $match->line,
            $traitShort,
        );

        return Warning::at(
            line: $match->line,
            message: $message,
            snippet: $match->content,
        );
    }

    private function opLabel(string $op): string
    {
        return match ($op) {
            'equals' => 'Equality',
            'not_equals' => 'Inequality',
            'one_of' => 'One-of',
            'not_one_of' => 'None-of',
            default => 'Equality',
        };
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

        $traitFqcn = $this->traitFqcn();
        $minChain = (int) $this->config('min_chain', 1);
        $pipe = $this->buildPipe($minChain);

        $pipeline = PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe);

        $ast = $pipeline->getContext()->ast;

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $uses = $this->extractUses($ast);
        $namespace = $this->extractNamespace($ast);
        $edits = [];
        $penance = [];

        foreach ($pipeline->getContext()->matches as $match) {
            $groups = $match->groups;

            $fromStatic = ($groups['from_static'] ?? '0') === '1';

            // Only primary-tier findings (enum uses the trait) are safe to
            // rewrite — the static call needs the trait's __callStatic. An
            // existing `Enum::equals(...)` static call already proves the trait
            // is in place, so it is always safe to re-anchor.
            if (! $fromStatic && $this->enumUsesTrait($groups['enum_fqcn'], $traitFqcn, $ast) !== true) {
                continue;
            }

            // #66: the operand is the enum's backing scalar — `equals(?Enum)`
            // would TypeError on it. Leave the `===`.
            if (! $fromStatic && $this->operandIsBackingScalar($groups['start'] ?? '', $ast, $uses, $namespace)) {
                continue;
            }

            // #57: never emit a call to a method the configured trait does not
            // define — that fatals at runtime. Leave it for the human (configure
            // the *_method names).
            if (! $this->traitDefinesMethod($traitFqcn, $this->methodFor($groups['op']))) {
                continue;
            }

            $start = (int) $groups['start'];
            $end = (int) $groups['end'];
            $replacement = $this->rewrite($groups);
            $original = substr($content, $start, $end - $start + 1);

            $edits[] = ['start' => $start, 'end' => $end, 'text' => $replacement];
            $penance[] = "Replaced `{$original}` with `{$replacement}`";
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    /**
     * Whether $fqcn is an actual enum — declared in the file under analysis or
     * resolvable as an enum. A class/interface/trait (or an unresolvable name,
     * e.g. a `self::` reference inside a non-enum class) is not.
     *
     * @param  array<Node>|null  $ast
     */
    private function isEnum(string $fqcn, ?array $ast): bool
    {
        $fqcn = ltrim($fqcn, '\\');

        if ($ast !== null && $this->enumDeclaredInAst($fqcn, $ast)) {
            return true;
        }

        return enum_exists($fqcn, autoload: true);
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enumDeclaredInAst(string $fqcn, array $ast): bool
    {
        foreach ($ast as $top) {
            if ($top instanceof Node\Stmt\Namespace_) {
                $ns = $top->name?->toString() ?? '';

                foreach ($top->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name !== null) {
                        $declared = $ns !== '' ? $ns . '\\' . $stmt->name->toString() : $stmt->name->toString();

                        if ($declared === $fqcn) {
                            return true;
                        }
                    }
                }
            } elseif ($top instanceof Node\Stmt\Enum_ && $top->name !== null && $top->name->toString() === $fqcn) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether the enum identified by $enumFqcn uses $traitFqcn.
     *
     * Order: in-file AST first (the file under analysis may declare the
     * enum), then reflection (autoloadable enums), then null when neither
     * can answer — the prophet treats null as "trait absent" and falls back
     * to the quieter adoption hint.
     *
     * @param  array<Node>|null  $ast
     */
    private function enumUsesTrait(string $enumFqcn, string $traitFqcn, ?array $ast): ?bool
    {
        $fromAst = $ast !== null ? $this->enumTraitFromAst($ast, $enumFqcn, $traitFqcn) : null;

        if ($fromAst !== null) {
            return $fromAst;
        }

        if (enum_exists($enumFqcn, autoload: true)) {
            try {
                $ref = new ReflectionEnum($enumFqcn);
                $traits = $ref->getTraitNames();

                foreach ($traits as $usedTrait) {
                    if (ltrim($usedTrait, '\\') === ltrim($traitFqcn, '\\')) {
                        return true;
                    }
                }

                return false;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Walk the file's AST for an enum declaration matching $enumFqcn. If
     * found, return whether it uses $traitFqcn. Returns null when the file
     * does not declare this enum.
     *
     * @param  array<Node>  $ast
     */
    private function enumTraitFromAst(array $ast, string $enumFqcn, string $traitFqcn): ?bool
    {
        $traitFqcn = ltrim($traitFqcn, '\\');
        $enumFqcn = ltrim($enumFqcn, '\\');

        $finder = new NodeFinder;

        foreach ($ast as $top) {
            if ($top instanceof Node\Stmt\Namespace_) {
                $ns = $top->name?->toString() ?? '';
                $uses = $this->collectUses($top->stmts);

                foreach ($top->stmts as $stmt) {
                    if (! $stmt instanceof Node\Stmt\Enum_ || $stmt->name === null) {
                        continue;
                    }

                    $declaredFqcn = $ns !== '' ? $ns . '\\' . $stmt->name->toString() : $stmt->name->toString();

                    if ($declaredFqcn !== $enumFqcn) {
                        continue;
                    }

                    return $this->enumNodeUsesTrait($stmt, $traitFqcn, $uses, $ns);
                }
            } elseif ($top instanceof Node\Stmt\Enum_ && $top->name !== null) {
                $uses = $this->collectUses($ast);
                $declaredFqcn = $top->name->toString();

                if ($declaredFqcn !== $enumFqcn) {
                    continue;
                }

                return $this->enumNodeUsesTrait($top, $traitFqcn, $uses, null);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $uses  alias => FQCN
     */
    private function enumNodeUsesTrait(Node\Stmt\Enum_ $enum, string $traitFqcn, array $uses, ?string $namespace): bool
    {
        foreach ($enum->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $traitName) {
                $resolved = $this->resolveTraitFqcn($traitName, $uses, $namespace);

                if ($resolved === ltrim($traitFqcn, '\\')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveTraitFqcn(Node\Name $name, array $uses, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($uses[$first])) {
            $parts[0] = $uses[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $fqcn;
            }
        }

        return $uses;
    }
}
