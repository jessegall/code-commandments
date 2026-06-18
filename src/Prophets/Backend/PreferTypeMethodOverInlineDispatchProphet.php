<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * Flag logic that is keyed off a type's own values but lives OUTSIDE the type:
 *
 *   - a `match`/`switch` that dispatches per enum case to a per-case result;
 *   - a ternary that maps a single type constant to a substitute value while
 *     passing every other value through (`$x === Type::CONST ? 'label' : $x`).
 *
 * Both belong ON the type — as a method (enum) or a small mapper (value class).
 */
#[IntroducedIn('1.58.0')]
class PreferTypeMethodOverInlineDispatchProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Move per-case dispatch and type-constant mappings onto the type, not inline at the call site';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `match`/`switch` maps two or more cases of one enum to per-case '
                . 'results, or a ternary substitutes a single type constant while '
                . 'passing other values through — behaviour keyed off a type that '
                . 'lives outside it, and usually duplicated across call sites.'
            )
            ->leaveWhen(
                'The branches are not per-case behaviour of one type (a `match (true)` '
                . 'guard, mixed subjects), or the logic genuinely needs collaborators '
                . 'the type should not depend on. A one-off with no duplication and a '
                . 'call-site-specific result can stay.'
            )
            ->whenUnsure(
                'If you can name a method on the type that returns the per-case '
                . 'result (`$op->evaluate(...)`, `Type::label($x)`), move it there. '
                . 'If the result is inherently about the caller, leave it.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Behaviour keyed off a type's own values belongs ON the type. Two shapes are
flagged.

1. A `match`/`switch` dispatching per enum case (>= 2 case arms of one enum):

   Bad — the dispatch lives in the caller:
       match ($operator) {
           CompareOperator::GreaterThan => (float) $a >  (float) $b,
           CompareOperator::Contains    => str_contains($a, $b),
           CompareOperator::Equals      => $a === $b,
           // …
       };

   Good — push it onto the enum and call it:
       enum CompareOperator {
           public function evaluate(mixed $a, mixed $b): bool {
               return match ($this) {
                   self::GreaterThan => (float) $a > (float) $b,
                   // …
               };
           }
       }
       $operator->evaluate($a, $b);

2. A ternary mapping ONE type constant to a substitute, passing the rest
   through — the degenerate single-case dispatch:

   Bad (and usually duplicated):
       $port->type === WireType::MIXED ? 'any' : $port->type

   Good — name the mapping on the type:
       WireType::label($port->type)   // returns 'any' for MIXED, else the type

WHAT FIRES:
  - `match`/`switch` whose subject is an enum and whose arms label >= 2 cases
    of that SAME enum (each mapped to its own result);
  - a ternary `S === Type::CONST ? X : S` (or `S !== Type::CONST ? S : X`)
    where one branch is the subject S unchanged — a constant-to-value map.

WHAT DOES NOT:
  - a `match (true)`/guard, or arms over mixed/non-enum subjects;
  - a `match`/`switch` INSIDE the enum's own file — that is the destination;
  - STRATEGY DISPATCH — arms that call the enclosing object's own methods
    (`Effect::Retype => $this->retypeInput()`). The behaviour lives on the
    caller; moving it onto the enum would invert the dependency (enum ->
    caller), so this is exempt;
  - CROSS-TYPE MAPPING — every arm produces a case/const of a DIFFERENT type
    (a domain enum translated to a presentation enum: `RunStatus::Ok =>
    LogLevel::Info`). A method on the matched enum would couple it to that
    other type; the map is a translation, not the enum's own behaviour. (Scalar
    labels — `St::A => 'a'` — are NOT exempt: `label()`/`weight()` belong on
    the enum.);
  - the ternary form on an ENUM constant — `$x === Enum::Case ? …` is the
    CompareSelf rule's territory (it routes through `equals`), so this rule
    only takes the ternary for NON-enum type constants (value classes).

Advisory, not auto-fixed: the method name and where collaborators come from
are semantic decisions.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $printer = new PrettyPrinter\Standard;
        $warnings = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope, $localEnums]) {
            $this->collectDispatch($scope, $namespace, $uses, $localEnums, $printer, $warnings);
            $this->collectSentinelTernary($scope, $namespace, $uses, $localEnums, $printer, $warnings);
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Detection 1 — `match`/`switch` dispatching per enum case.
     *
     * @param  array<Node>  $scope
     * @param  array<string, string>  $uses
     * @param  array<string, true>  $localEnums
     * @param  list<Warning>  $warnings
     */
    private function collectDispatch(array $scope, ?string $namespace, array $uses, array $localEnums, PrettyPrinter\Standard $printer, array &$warnings): void
    {
        $finder = new NodeFinder;

        // Dispatches lexically INSIDE an enum declaration are the destination
        // (a `match ($this)` method), keyed by the enum they live in.
        $insideEnum = $this->dispatchesInsideEnums($scope, $namespace, $finder);

        /** @var array<Expr\Match_|Node\Stmt\Switch_> $nodes */
        $nodes = $finder->find($scope, fn (Node $n): bool => $n instanceof Expr\Match_ || $n instanceof Node\Stmt\Switch_);

        foreach ($nodes as $node) {
            $conds = $node instanceof Expr\Match_
                ? $this->matchArmConds($node)
                : $this->switchCaseConds($node);

            $enum = $this->singleEnumOf($conds, $uses, $namespace);

            if ($enum === null) {
                continue;
            }

            // Skip when this dispatch lives inside the very enum it dispatches
            // on — that per-case method IS the prescribed fix. This covers both
            // `match ($this) { WireCategory::Mixed => … }` (arms resolve to the
            // enum) and `match ($this) { self::Mixed => … }` (arms resolve to a
            // `self` pseudo-FQCN, which always refers to the enclosing enum).
            $insideFqcn = $insideEnum[spl_object_id($node)] ?? null;

            if ($insideFqcn !== null
                && ($insideFqcn === $enum['fqcn'] || in_array($enum['short'], ['self', 'static'], true))
            ) {
                continue;
            }

            // STRATEGY DISPATCH — arms call the enclosing object's own methods
            // (`=> $this->handle(...)`). Pushing these onto the enum would force
            // the enum to depend on its caller (inverted layering), so this is
            // not "behaviour that belongs on the enum". Exempt.
            if ($this->armsDispatchToThis($node, $finder)) {
                continue;
            }

            // CROSS-TYPE MAPPING — every arm produces a case/const of a DIFFERENT
            // type (e.g. a domain enum mapped to a presentation enum). A method
            // on the matched enum would make it depend on that other type; the
            // map is a translation, not the enum's own behaviour. (Scalar labels
            // — string/int — are NOT exempt: `label()`/`weight()` do belong on
            // the enum.) Exempt.
            if ($this->armsMapToForeignType($node, $uses, $namespace, $enum['fqcn'])) {
                continue;
            }

            $subject = $printer->prettyPrintExpr($node->cond);

            $warnings[] = $this->warningAt(
                $node->getStartLine(),
                sprintf(
                    '%s on %s dispatches %d cases of %s to per-case results — that behaviour belongs on the enum. '
                    . 'Give %s a method (e.g. `evaluate(...)`/`label(...)`) and call `%s->method(...)` instead.',
                    $node instanceof Expr\Match_ ? 'match' : 'switch',
                    $subject,
                    $enum['count'],
                    $enum['short'],
                    $enum['short'],
                    $subject,
                ),
                null,
                'inline-dispatch:' . $enum['fqcn'],
            );
        }
    }

    /**
     * Whether any arm body calls a method on `$this` — strategy dispatch to the
     * enclosing object's own behaviour, which must not be pushed onto the enum.
     */
    private function armsDispatchToThis(Node $node, NodeFinder $finder): bool
    {
        foreach ($this->armBodies($node) as $body) {
            $hit = $finder->findFirst($body, static fn (Node $n): bool =>
                $n instanceof Expr\MethodCall
                && $n->var instanceof Expr\Variable
                && $n->var->name === 'this');

            if ($hit !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every arm produces a class-constant / enum-case of a type OTHER
     * than the matched enum — a cross-type translation, not the enum's own
     * behaviour. A scalar result (string/int label) or any non-constant result
     * (closure, call, throw) makes this false, so the canonical
     * `label()`/`evaluate()` cases stay flagged.
     *
     * @param  array<string, string>  $uses
     */
    private function armsMapToForeignType(Node $node, array $uses, ?string $namespace, string $matchedFqcn): bool
    {
        $results = $this->armResultExprs($node);

        if ($results === []) {
            return false;
        }

        $sawForeign = false;

        foreach ($results as $expr) {
            if (! $expr instanceof Expr\ClassConstFetch || ! $expr->class instanceof Node\Name) {
                return false;
            }

            if ($this->resolveFqcn($expr->class, $uses, $namespace) === $matchedFqcn) {
                return false;
            }

            $sawForeign = true;
        }

        return $sawForeign;
    }

    /**
     * The statement/expression bodies of a match/switch's arms (for scanning).
     *
     * @return list<Node>
     */
    private function armBodies(Node $node): array
    {
        if ($node instanceof Expr\Match_) {
            return array_map(static fn (Node\MatchArm $arm): Node => $arm->body, $node->arms);
        }

        if ($node instanceof Node\Stmt\Switch_) {
            $bodies = [];

            foreach ($node->cases as $case) {
                foreach ($case->stmts as $stmt) {
                    $bodies[] = $stmt;
                }
            }

            return $bodies;
        }

        return [];
    }

    /**
     * The result expressions each arm produces: a match arm's body, or the
     * `return` expression of a switch case.
     *
     * @return list<Expr>
     */
    private function armResultExprs(Node $node): array
    {
        if ($node instanceof Expr\Match_) {
            return array_map(static fn (Node\MatchArm $arm): Expr => $arm->body, $node->arms);
        }

        $exprs = [];

        if ($node instanceof Node\Stmt\Switch_) {
            foreach ($node->cases as $case) {
                foreach ($case->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr !== null) {
                        $exprs[] = $stmt->expr;
                    }
                }
            }
        }

        return $exprs;
    }

    /**
     * Map of `match`/`switch` node object-id => FQCN of the enum it is declared
     * inside, for dispatches that live within an enum body.
     *
     * @param  array<Node>  $scope
     * @return array<int, string>
     */
    private function dispatchesInsideEnums(array $scope, ?string $namespace, NodeFinder $finder): array
    {
        $inside = [];

        /** @var array<Node\Stmt\Enum_> $enums */
        $enums = $finder->findInstanceOf($scope, Node\Stmt\Enum_::class);

        foreach ($enums as $enum) {
            if ($enum->name === null) {
                continue;
            }

            $short = $enum->name->toString();
            $fqcn = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $short : $short;

            $dispatches = $finder->find($enum->stmts, fn (Node $n): bool => $n instanceof Expr\Match_ || $n instanceof Node\Stmt\Switch_);

            foreach ($dispatches as $dispatch) {
                $inside[spl_object_id($dispatch)] = $fqcn;
            }
        }

        return $inside;
    }

    /**
     * Detection 2 — `S === Type::CONST ? X : S` constant-to-value mapping on a
     * NON-enum type (value class). Enum constants are the CompareSelf rule's
     * territory, so they are skipped here.
     *
     * @param  array<Node>  $scope
     * @param  array<string, string>  $uses
     * @param  array<string, true>  $localEnums
     * @param  list<Warning>  $warnings
     */
    private function collectSentinelTernary(array $scope, ?string $namespace, array $uses, array $localEnums, PrettyPrinter\Standard $printer, array &$warnings): void
    {
        $finder = new NodeFinder;

        /** @var array<Expr\Ternary> $ternaries */
        $ternaries = $finder->findInstanceOf($scope, Expr\Ternary::class);

        foreach ($ternaries as $ternary) {
            if ($ternary->if === null) {
                continue; // short ternary `?:` cannot carry the substitution shape
            }

            $cond = $ternary->cond;

            if (! $cond instanceof Expr\BinaryOp\Identical && ! $cond instanceof Expr\BinaryOp\NotIdentical) {
                continue;
            }

            [$subject, $constFetch] = $this->orientConstComparison($cond);

            if ($constFetch === null) {
                continue;
            }

            $className = $constFetch->class;

            if (! $className instanceof Node\Name) {
                continue;
            }

            $fqcn = $this->resolveFqcn($className, $uses, $namespace);

            // Enum constants belong to the CompareSelf rule.
            if (isset($localEnums[$fqcn]) || $this->isEnum($fqcn)) {
                continue;
            }

            // The "pass-through" branch must be the subject itself: for `===`
            // the else branch, for `!==` the then branch.
            $passThrough = $cond instanceof Expr\BinaryOp\Identical ? $ternary->else : $ternary->if;

            if ($printer->prettyPrintExpr($passThrough) !== $printer->prettyPrintExpr($subject)) {
                continue;
            }

            $short = $className->getLast();
            $const = $constFetch->name instanceof Node\Identifier ? $constFetch->name->toString() : 'CONST';

            $warnings[] = $this->warningAt(
                $ternary->getStartLine(),
                sprintf(
                    'Inline mapping of `%s::%s` to a value, passing other values through — this is a property of %s. '
                    . 'Name it on the type (e.g. `%s::label(%s)`) instead of inlining the ternary.',
                    $short,
                    $const,
                    $short,
                    $short,
                    $printer->prettyPrintExpr($subject),
                ),
                null,
                'inline-const-map:' . $fqcn . '::' . $const,
            );
        }
    }

    /**
     * @return array{0: Expr, 1: Expr\ClassConstFetch|null}
     */
    private function orientConstComparison(Expr\BinaryOp $cond): array
    {
        if ($this->isConstFetch($cond->right)) {
            /** @var Expr\ClassConstFetch $right */
            $right = $cond->right;

            return [$cond->left, $right];
        }

        if ($this->isConstFetch($cond->left)) {
            /** @var Expr\ClassConstFetch $left */
            $left = $cond->left;

            return [$cond->right, $left];
        }

        return [$cond->left, null];
    }

    private function isConstFetch(Node $node): bool
    {
        return $node instanceof Expr\ClassConstFetch
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && strtolower($node->name->toString()) !== 'class';
    }

    /**
     * The enum all the case-fetch conditions belong to, or null when the
     * conditions are not >= 2 case fetches of one enum.
     *
     * @param  list<Expr>  $conds
     * @param  array<string, string>  $uses
     * @return array{fqcn: string, short: string, count: int}|null
     */
    private function singleEnumOf(array $conds, array $uses, ?string $namespace): ?array
    {
        $fqcn = null;
        $short = null;
        $count = 0;

        foreach ($conds as $cond) {
            if (! $this->isConstFetch($cond)) {
                return null; // a non-case arm means it is not pure per-case dispatch
            }

            /** @var Expr\ClassConstFetch $cond */
            /** @var Node\Name $class */
            $class = $cond->class;
            $resolved = $this->resolveFqcn($class, $uses, $namespace);

            if ($fqcn === null) {
                $fqcn = $resolved;
                $short = $class->getLast();
            } elseif ($fqcn !== $resolved) {
                return null; // mixed types
            }

            $count++;
        }

        if ($fqcn === null || $short === null || $count < 2) {
            return null;
        }

        return ['fqcn' => $fqcn, 'short' => $short, 'count' => $count];
    }

    /**
     * @return list<Expr>
     */
    private function matchArmConds(Expr\Match_ $match): array
    {
        $conds = [];

        foreach ($match->arms as $arm) {
            if ($arm->conds === null) {
                continue; // default arm
            }

            foreach ($arm->conds as $cond) {
                $conds[] = $cond;
            }
        }

        return $conds;
    }

    /**
     * @return list<Expr>
     */
    private function switchCaseConds(Node\Stmt\Switch_ $switch): array
    {
        $conds = [];

        foreach ($switch->cases as $case) {
            if ($case->cond !== null) {
                $conds[] = $case->cond;
            }
        }

        return $conds;
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>, 3: array<string, true>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $uses = $this->collectUses($scope);
            $localEnums = [];

            foreach ($scope as $stmt) {
                if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name !== null) {
                    $short = $stmt->name->toString();
                    $localEnums[$namespace !== null && $namespace !== '' ? $namespace . '\\' . $short : $short] = true;
                }
            }

            $out[] = [$namespace, $uses, $scope, $localEnums];
        }

        return $out;
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
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $useUse->name->toString();
            }
        }

        return $uses;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveFqcn(Node\Name $name, array $uses, ?string $namespace): string
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

        if ($namespace !== null && $namespace !== '' && ! in_array($first, ['self', 'static', 'parent'], true)) {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    private function isEnum(string $fqcn): bool
    {
        return enum_exists(ltrim($fqcn, '\\'), autoload: true);
    }
}
