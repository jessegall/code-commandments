<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * Flag nullable parameters / properties that should hold a Null Object
 * default instead.
 *
 * Two related patterns:
 *
 *   A. Body-normalized nullable param (sin). A parameter typed `T|null`
 *      with a `= null` default whose method body's first reference to
 *      it is a normalization to a non-null value — proof that null is
 *      never an accepted state. Sub-shapes:
 *
 *        A1 — RHS is a constant expression PHP allows as a default
 *             (literal, Enum::CASE, Class::CONST, `new C(...constArgs)`).
 *             Auto-fixable: hoist the RHS to the parameter default.
 *
 *        A2 — RHS is a closure literal (`fn () => …`, `function () {}`).
 *             PHP forbids closures as defaults. Fixable when the param
 *             type appears in the configured null-object map (e.g.
 *             `callable => App\Support\NullCallable`); warning
 *             otherwise.
 *
 *        A3 — RHS is a runtime call (`$this->resolve()`, `app(...)`).
 *             Silent — there's a real reason the default is computed.
 *
 *   B. Null-safe-chain on nullable receiver (warning). A property,
 *      parameter, or variable typed `T|null` that gets consumed via
 *      `?->` two or more times in the same scope with no meaningful
 *      null branch. Suggests a Null Object so the call sites stop
 *      asking "is it null?" at every step.
 *
 * Match names emitted:
 *   - pattern_a1
 *   - pattern_a2_known
 *   - pattern_a2_unknown
 *   - pattern_b
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindNullObjectDefaultsCandidates implements Pipe
{
    /**
     * Type aliases (`callable`) or FQCN keys mapped to the Null Object
     * class FQCN that should replace `null` as the default.
     *
     * @var array<string, string>
     */
    private array $nullObjectMap = [];

    /**
     * Concrete types Pattern B should leave alone — `T|null` here usually
     * means "genuinely optional value", not "no-op default".
     *
     * @var array<string>
     */
    private const PATTERN_B_TYPE_WHITELIST = [
        'DateTimeImmutable',
        'DateTime',
        'DateTimeInterface',
        'BackedEnum',
        'UnitEnum',
    ];

    /**
     * Spatie LaravelData wrappers we never flag for either pattern —
     * those types intentionally use null defaults inside Data classes.
     *
     * @var array<string>
     */
    private const SPATIE_DATA_WHITELIST = [
        'Spatie\\LaravelData\\Lazy',
        'Spatie\\LaravelData\\Optional',
        'Lazy',
        'Optional',
    ];

    private int $minNullsafeAccesses = 2;

    private ?PrettyPrinter\Standard $printer = null;

    /**
     * @param  array<string, string>  $map
     */
    public function withNullObjectMap(array $map): self
    {
        $this->nullObjectMap = $map;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $matches = [];
        $nodeFinder = new NodeFinder;

        foreach ($nodeFinder->findInstanceOf($input->ast, Stmt\ClassMethod::class) as $method) {
            assert($method instanceof Stmt\ClassMethod);

            if ($method->stmts === null) {
                continue;
            }

            if ($this->methodUsesReflectionTricks($method)) {
                continue;
            }

            foreach ($this->detectPatternA($method, $input) as $match) {
                $matches[] = $match;
            }

            foreach ($this->detectPatternB($method, $input) as $match) {
                $matches[] = $match;
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * @return iterable<MatchResult>
     */
    private function detectPatternA(Stmt\ClassMethod $method, PhpContext $input): iterable
    {
        $normalizations = $this->collectLeadingNormalizations($method->stmts ?? []);

        if ($normalizations === []) {
            return;
        }

        $assignmentsByName = $this->collectAssignmentsByName($method->stmts ?? []);

        foreach ($method->params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $paramName = $param->var->name;

            if (! isset($normalizations[$paramName])) {
                continue;
            }

            $typeInfo = $this->extractNullableTypeInfo($param->type);

            if ($typeInfo === null) {
                continue;
            }

            if (! $param->default instanceof Expr\ConstFetch
                || strtolower($param->default->name->toString()) !== 'null'
            ) {
                continue;
            }

            if (in_array($typeInfo['typeName'], self::SPATIE_DATA_WHITELIST, true)) {
                continue;
            }

            if ($this->paramReassignedToNull($paramName, $assignmentsByName, $normalizations[$paramName]['stmt'])) {
                continue;
            }

            [$kind, $rhs] = $this->classifyRhs($normalizations[$paramName]['rhs'], $typeInfo['typeName']);

            if ($kind === 'a3_skip') {
                continue;
            }

            $matches[] = $this->makePatternAMatch(
                kind: $kind,
                method: $method,
                param: $param,
                typeInfo: $typeInfo,
                rhs: $rhs,
                normalizationStmt: $normalizations[$paramName]['stmt'],
                content: $input->content,
            );
        }

        yield from $matches ?? [];
    }

    /**
     * @return iterable<MatchResult>
     */
    private function detectPatternB(Stmt\ClassMethod $method, PhpContext $input): iterable
    {
        $classNode = $this->findEnclosingClass($method, $input);

        $receivers = $this->collectNullsafeReceivers($method);

        foreach ($receivers as $key => $entry) {
            if ($entry['count'] < $this->minNullsafeAccesses) {
                continue;
            }

            $typeInfo = $this->resolveReceiverType($entry['expr'], $method, $classNode);

            if ($typeInfo === null) {
                continue;
            }

            if (! $typeInfo['nullable']) {
                continue;
            }

            if (in_array($typeInfo['typeName'], self::PATTERN_B_TYPE_WHITELIST, true)) {
                continue;
            }

            if (in_array($typeInfo['typeName'], self::SPATIE_DATA_WHITELIST, true)) {
                continue;
            }

            if ($this->hasMeaningfulNullBranch($entry['expr'], $method)) {
                continue;
            }

            yield $this->makePatternBMatch(
                method: $method,
                entry: $entry,
                typeInfo: $typeInfo,
                content: $input->content,
            );
        }
    }

    /**
     * Walk the leading statements of $stmts collecting `$param ??= EXPR`,
     * `$param = $param ?? EXPR`, and `if ($param === null) $param = EXPR;`.
     * Stops at the first non-normalization statement.
     *
     * @param  array<Stmt>  $stmts
     * @return array<string, array{rhs: Expr, stmt: Stmt}>
     */
    private function collectLeadingNormalizations(array $stmts): array
    {
        $normalizations = [];

        foreach ($stmts as $stmt) {
            $result = $this->matchNormalization($stmt);

            if ($result === null) {
                break;
            }

            [$name, $rhs] = $result;

            if (isset($normalizations[$name])) {
                break;
            }

            $normalizations[$name] = ['rhs' => $rhs, 'stmt' => $stmt];
        }

        return $normalizations;
    }

    /**
     * @return array{0: string, 1: Expr}|null  [paramName, rhsExpression]
     */
    private function matchNormalization(Stmt $stmt): ?array
    {
        if ($stmt instanceof Stmt\Expression) {
            $inner = $stmt->expr;

            if ($inner instanceof Expr\AssignOp\Coalesce
                && $inner->var instanceof Expr\Variable
                && is_string($inner->var->name)
            ) {
                return [$inner->var->name, $inner->expr];
            }

            if ($inner instanceof Expr\Assign
                && $inner->var instanceof Expr\Variable
                && is_string($inner->var->name)
                && $inner->expr instanceof Expr\BinaryOp\Coalesce
                && $inner->expr->left instanceof Expr\Variable
                && is_string($inner->expr->left->name)
                && $inner->expr->left->name === $inner->var->name
            ) {
                return [$inner->var->name, $inner->expr->right];
            }
        }

        if ($stmt instanceof Stmt\If_
            && $stmt->else === null
            && $stmt->elseifs === []
            && count($stmt->stmts) === 1
        ) {
            $cond = $stmt->cond;
            $paramName = null;

            if ($cond instanceof Expr\BinaryOp\Identical
                || $cond instanceof Expr\BinaryOp\Equal
            ) {
                $paramName = $this->paramNameInNullCheck($cond->left, $cond->right);
            }

            if ($paramName === null) {
                return null;
            }

            $body = $stmt->stmts[0];

            if (! $body instanceof Stmt\Expression) {
                return null;
            }

            if (! $body->expr instanceof Expr\Assign
                || ! $body->expr->var instanceof Expr\Variable
                || $body->expr->var->name !== $paramName
            ) {
                return null;
            }

            return [$paramName, $body->expr->expr];
        }

        return null;
    }

    private function paramNameInNullCheck(Expr $left, Expr $right): ?string
    {
        if ($left instanceof Expr\Variable
            && is_string($left->name)
            && $right instanceof Expr\ConstFetch
            && strtolower($right->name->toString()) === 'null'
        ) {
            return $left->name;
        }

        if ($right instanceof Expr\Variable
            && is_string($right->name)
            && $left instanceof Expr\ConstFetch
            && strtolower($left->name->toString()) === 'null'
        ) {
            return $right->name;
        }

        return null;
    }

    /**
     * Build a lookup of `$var = …` assignments per variable name across the
     * full method body — used to confirm the param is never re-set to null
     * after the normalization.
     *
     * @param  array<Stmt>  $stmts
     * @return array<string, array<Expr\Assign>>
     */
    private function collectAssignmentsByName(array $stmts): array
    {
        $byName = [];
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($stmts, Expr\Assign::class) as $assign) {
            assert($assign instanceof Expr\Assign);

            if ($assign->var instanceof Expr\Variable && is_string($assign->var->name)) {
                $byName[$assign->var->name][] = $assign;
            }
        }

        return $byName;
    }

    /**
     * @param  array<string, array<Expr\Assign>>  $assignmentsByName
     */
    private function paramReassignedToNull(string $name, array $assignmentsByName, Stmt $normalizationStmt): bool
    {
        $normalizationLine = $normalizationStmt->getStartLine();

        foreach ($assignmentsByName[$name] ?? [] as $assign) {
            if ($assign->getStartLine() <= $normalizationLine) {
                continue;
            }

            if ($assign->expr instanceof Expr\ConstFetch
                && strtolower($assign->expr->name->toString()) === 'null'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{typeName: string, nullable: bool, fqcnHint: ?string}|null
     */
    private function extractNullableTypeInfo(?Node $type): ?array
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof Node\NullableType) {
            $inner = $type->type;

            if ($inner instanceof Node\Identifier) {
                return [
                    'typeName' => $inner->toString(),
                    'nullable' => true,
                    'fqcnHint' => null,
                ];
            }

            if ($inner instanceof Node\Name) {
                return [
                    'typeName' => $inner->toString(),
                    'nullable' => true,
                    'fqcnHint' => $inner->toString(),
                ];
            }

            return null;
        }

        if ($type instanceof Node\UnionType) {
            $nonNull = [];
            $hasNull = false;

            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    $hasNull = true;
                    continue;
                }

                if ($member instanceof Node\Name && strtolower($member->toString()) === 'null') {
                    $hasNull = true;
                    continue;
                }

                $nonNull[] = $member;
            }

            if (! $hasNull || count($nonNull) !== 1) {
                return null;
            }

            $only = $nonNull[0];

            if ($only instanceof Node\Identifier) {
                return ['typeName' => $only->toString(), 'nullable' => true, 'fqcnHint' => null];
            }

            if ($only instanceof Node\Name) {
                return ['typeName' => $only->toString(), 'nullable' => true, 'fqcnHint' => $only->toString()];
            }

            return null;
        }

        if ($type instanceof Node\Identifier) {
            return ['typeName' => $type->toString(), 'nullable' => false, 'fqcnHint' => null];
        }

        if ($type instanceof Node\Name) {
            return ['typeName' => $type->toString(), 'nullable' => false, 'fqcnHint' => $type->toString()];
        }

        return null;
    }

    /**
     * Decide whether a normalization RHS is auto-fix-friendly (A1), needs
     * the configured null-object map (A2), or is runtime-computed (A3).
     *
     * @return array{0: string, 1: Expr}
     */
    private function classifyRhs(Expr $rhs, string $paramTypeName): array
    {
        if ($this->isConstantDefaultExpression($rhs)) {
            return ['a1', $rhs];
        }

        if ($rhs instanceof Expr\Closure || $rhs instanceof Expr\ArrowFunction) {
            $mapKey = $this->resolveMapKey($paramTypeName);

            if ($mapKey !== null) {
                return ['a2_known', $rhs];
            }

            return ['a2_unknown', $rhs];
        }

        return ['a3_skip', $rhs];
    }

    /**
     * Does the expression match PHP's parameter-default grammar? Literal
     * scalars, unary minus on scalars, `Class::CONST`, `Enum::CASE`,
     * and `new Class(...constArgs)` all qualify; closures and runtime
     * calls do not.
     */
    private function isConstantDefaultExpression(Expr $expr): bool
    {
        if ($expr instanceof Node\Scalar) {
            return true;
        }

        if ($expr instanceof Expr\ConstFetch || $expr instanceof Expr\ClassConstFetch) {
            return true;
        }

        if ($expr instanceof Expr\UnaryMinus || $expr instanceof Expr\UnaryPlus) {
            return $this->isConstantDefaultExpression($expr->expr);
        }

        if ($expr instanceof Expr\Array_) {
            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }

                if (! $this->isConstantDefaultExpression($item->value)) {
                    return false;
                }

                if ($item->key !== null && ! $this->isConstantDefaultExpression($item->key)) {
                    return false;
                }
            }

            return true;
        }

        if ($expr instanceof Expr\New_) {
            if (! $expr->class instanceof Node\Name) {
                return false;
            }

            foreach ($expr->args as $arg) {
                if (! $arg instanceof Node\Arg) {
                    return false;
                }

                if (! $this->isConstantDefaultExpression($arg->value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Resolve a config key (`callable`, `LoggerInterface`, or FQCN) into
     * the matching map entry. Returns the key that hit, not the value.
     */
    private function resolveMapKey(string $paramTypeName): ?string
    {
        if (isset($this->nullObjectMap[$paramTypeName])) {
            return $paramTypeName;
        }

        $lower = strtolower($paramTypeName);

        foreach ($this->nullObjectMap as $key => $_) {
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array{typeName: string, nullable: bool, fqcnHint: ?string}  $typeInfo
     */
    private function makePatternAMatch(
        string $kind,
        Stmt\ClassMethod $method,
        Node\Param $param,
        array $typeInfo,
        Expr $rhs,
        Stmt $normalizationStmt,
        string $content,
    ): MatchResult {
        $matchName = match ($kind) {
            'a1' => 'pattern_a1',
            'a2_known' => 'pattern_a2_known',
            'a2_unknown' => 'pattern_a2_unknown',
        };

        $rhsSource = $this->renderExpr($rhs);
        $paramName = $param->var instanceof Expr\Variable && is_string($param->var->name)
            ? $param->var->name
            : '?';

        $nullObjectFqcn = '';

        if ($kind === 'a2_known') {
            $mapKey = $this->resolveMapKey($typeInfo['typeName']);
            $nullObjectFqcn = $mapKey !== null ? $this->nullObjectMap[$mapKey] : '';
        }

        $line = $param->getStartLine();

        return new MatchResult(
            name: $matchName,
            pattern: '',
            match: "\${$paramName}",
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [
                'subject' => "\${$paramName}",
                'method' => $method->name->toString(),
                'type_name' => $typeInfo['typeName'],
                'rhs_source' => $rhsSource,
                'null_object_fqcn' => $nullObjectFqcn,
                'normalization_line' => (string) $normalizationStmt->getStartLine(),
            ],
        );
    }

    /**
     * Collect every `?->` access keyed by the receiver expression (e.g.
     * `$this->logger`, `$observer`). Tracks how many times each
     * receiver was accessed.
     *
     * @return array<string, array{expr: Expr, count: int, firstLine: int}>
     */
    private function collectNullsafeReceivers(Stmt\ClassMethod $method): array
    {
        $receivers = [];
        $finder = new NodeFinder;

        $found = $finder->find($method->stmts ?? [], static function (Node $n) {
            return $n instanceof Expr\NullsafeMethodCall || $n instanceof Expr\NullsafePropertyFetch;
        });

        foreach ($found as $node) {
            assert($node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\NullsafePropertyFetch);

            $receiver = $node->var;
            $key = $this->keyForReceiver($receiver);

            if ($key === null) {
                continue;
            }

            if (! isset($receivers[$key])) {
                $receivers[$key] = [
                    'expr' => $receiver,
                    'count' => 0,
                    'firstLine' => $node->getStartLine(),
                ];
            }

            $receivers[$key]['count']++;
        }

        return $receivers;
    }

    private function keyForReceiver(Expr $expr): ?string
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return 'var:' . $expr->name;
        }

        if ($expr instanceof Expr\PropertyFetch
            && $expr->var instanceof Expr\Variable
            && is_string($expr->var->name)
            && $expr->name instanceof Node\Identifier
        ) {
            return 'prop:' . $expr->var->name . '->' . $expr->name->toString();
        }

        return null;
    }

    /**
     * Resolve the declared type of a receiver expression. Looks at param
     * declarations and promoted/regular properties on the enclosing class.
     *
     * @return array{typeName: string, nullable: bool}|null
     */
    private function resolveReceiverType(Expr $expr, Stmt\ClassMethod $method, ?Stmt\Class_ $class): ?array
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            foreach ($method->params as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $expr->name) {
                    return $this->plainTypeInfo($param->type);
                }
            }

            return null;
        }

        if ($expr instanceof Expr\PropertyFetch
            && $expr->var instanceof Expr\Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
            && $class !== null
        ) {
            return $this->findPropertyType($expr->name->toString(), $class);
        }

        return null;
    }

    /**
     * @return array{typeName: string, nullable: bool}|null
     */
    private function plainTypeInfo(?Node $type): ?array
    {
        $info = $this->extractNullableTypeInfo($type);

        if ($info === null) {
            return null;
        }

        return ['typeName' => $info['typeName'], 'nullable' => $info['nullable']];
    }

    /**
     * @return array{typeName: string, nullable: bool}|null
     */
    private function findPropertyType(string $propertyName, Stmt\Class_ $class): ?array
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property) {
                foreach ($stmt->props as $propProp) {
                    if ($propProp->name->toString() === $propertyName) {
                        return $this->plainTypeInfo($stmt->type);
                    }
                }
            }
        }

        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() !== '__construct') {
                continue;
            }

            foreach ($method->params as $param) {
                if (($param->flags & Node\Stmt\Class_::VISIBILITY_MODIFIER_MASK) === 0) {
                    continue;
                }

                if ($param->var instanceof Expr\Variable && $param->var->name === $propertyName) {
                    return $this->plainTypeInfo($param->type);
                }
            }
        }

        return null;
    }

    /**
     * Does the method body contain an explicit `if ($x === null)` (or
     * similar) branch handling the receiver as a real null case? When
     * yes, Pattern B should NOT fire — null is meaningful here.
     */
    private function hasMeaningfulNullBranch(Expr $receiver, Stmt\ClassMethod $method): bool
    {
        $key = $this->keyForReceiver($receiver);

        if ($key === null) {
            return false;
        }

        $finder = new NodeFinder;

        $checks = $finder->find($method->stmts ?? [], function (Node $n) use ($key) {
            if (! $n instanceof Expr\BinaryOp\Identical && ! $n instanceof Expr\BinaryOp\NotIdentical
                && ! $n instanceof Expr\BinaryOp\Equal && ! $n instanceof Expr\BinaryOp\NotEqual
            ) {
                return false;
            }

            $other = $this->matchedSideOfNullComparison($n);

            return $other !== null && $this->keyForReceiver($other) === $key;
        });

        return ! empty($checks);
    }

    private function matchedSideOfNullComparison(Expr\BinaryOp $op): ?Expr
    {
        if ($op->right instanceof Expr\ConstFetch && strtolower($op->right->name->toString()) === 'null') {
            return $op->left;
        }

        if ($op->left instanceof Expr\ConstFetch && strtolower($op->left->name->toString()) === 'null') {
            return $op->right;
        }

        return null;
    }

    /**
     * @param  array{expr: Expr, count: int, firstLine: int}  $entry
     * @param  array{typeName: string, nullable: bool}  $typeInfo
     */
    private function makePatternBMatch(
        Stmt\ClassMethod $method,
        array $entry,
        array $typeInfo,
        string $content,
    ): MatchResult {
        $subject = $this->renderExpr($entry['expr']);
        $line = $entry['firstLine'];
        $mapKey = $this->resolveMapKey($typeInfo['typeName']);
        $suggestedFqcn = $mapKey !== null ? $this->nullObjectMap[$mapKey] : '';

        return new MatchResult(
            name: 'pattern_b',
            pattern: '',
            match: $subject,
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [
                'subject' => $subject,
                'method' => $method->name->toString(),
                'type_name' => $typeInfo['typeName'],
                'access_count' => (string) $entry['count'],
                'null_object_fqcn' => $suggestedFqcn,
            ],
        );
    }

    private function findEnclosingClass(Stmt\ClassMethod $method, PhpContext $input): ?Stmt\Class_
    {
        foreach ($input->classes as $class) {
            foreach ($class->getMethods() as $candidate) {
                if ($candidate === $method) {
                    return $class;
                }
            }
        }

        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($input->ast ?? [], Stmt\Class_::class) as $class) {
            assert($class instanceof Stmt\Class_);

            foreach ($class->getMethods() as $candidate) {
                if ($candidate === $method) {
                    return $class;
                }
            }
        }

        return null;
    }

    private function methodUsesReflectionTricks(Stmt\ClassMethod $method): bool
    {
        $finder = new NodeFinder;

        $calls = $finder->find($method->stmts ?? [], static function (Node $n) {
            if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name) {
                $name = strtolower($n->name->toString());

                return in_array($name, ['func_get_args', 'func_num_args', 'func_get_arg'], true);
            }

            return false;
        });

        return ! empty($calls);
    }

    private function renderExpr(Expr $expr): string
    {
        if ($this->printer === null) {
            $this->printer = new PrettyPrinter\Standard;
        }

        return $this->printer->prettyPrintExpr($expr);
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
