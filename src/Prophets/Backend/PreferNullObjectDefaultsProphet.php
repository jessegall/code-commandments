<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClass;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindNullObjectDefaultsCandidates;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

/**
 * Surface nullable params/properties that should hold a Null Object default
 * instead.
 *
 * The prophet emits both severities from one run:
 *
 *   - Pattern A (sin) — `T|null $param = null` whose body immediately
 *     normalizes to a non-null default. The signature lies; the body
 *     proves null is never accepted. Often auto-fixable.
 *
 *   - Pattern B (warning) — a `T|null` symbol consumed via `?->` two or
 *     more times in the same scope with no explicit null branch. The
 *     `?->` chain morally means "I never act differently on null" — a
 *     Null Object default removes every check.
 *
 * The configurable `null_objects` map lets the prophet pick the right
 * replacement class for known types (e.g. `callable => NullCallable`,
 * `LoggerInterface => NullLogger`).
 *
 *
 *
 * @method-generated-start
 * @method static nullObjects(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.13.0')]
class PreferNullObjectDefaultsProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Prefer Null Object defaults over nullable params normalized in the body';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A common shape in long-lived classes looks like:

    public function run(
        callable | null $shouldExit = null,
        Observer | null $observer = null,
    ): void {
        $shouldExit ??= static fn () => false;
        $observer ??= new NullObserver;
        // ...
    }

Three problems with that:

  1. The signature lies. It says "null is acceptable" when the body
     refuses null and replaces it immediately.

  2. The default is hidden inside the body. A caller reading the
     signature has to scroll into the implementation to see what null
     actually means.

  3. `??=` is read at every call — runtime branching for a constant.

The cleaner shape uses Null Object defaults:

    public function run(
        callable $shouldExit = new NullCallable,
        Observer $observer = new NullObserver,
    ): void {
        // No normalization needed; default is at the signature.
    }

This prophet flags two related patterns.

PATTERN A — body-normalized nullable param (sin).

A parameter typed `T|null` (or `?T`) with `= null` default whose
method body's first reference to it is one of:

  - `$param ??= EXPR`
  - `$param = $param ?? EXPR`
  - `if ($param === null) { $param = EXPR; }`

After the normalization the param is never re-assigned to null —
proof the signature shouldn't have included null at all.

Three sub-shapes based on the RHS:

  A1. Constant default expression (literal, `Class::CONST`,
      `Enum::CASE`, `new C(...constArgs)`). Auto-fix moves the
      RHS to the parameter default and drops `T|null` to `T`.

  A2. Closure literal. PHP forbids closures as parameter defaults.
      When the param type appears in the configured `null_objects`
      map (e.g. `callable => NullCallable`), the auto-fix uses that
      Null Object. Otherwise, suggest creating one.

  A3. Runtime call (`$this->resolveX()`, `app(Foo::class)`). Silent
      — there's a real reason the value is computed at runtime.

PATTERN B — null-safe-chain on nullable receiver (warning).

A property, parameter, or variable typed `T|null` accessed via
`?->` two or more times in the same scope with no meaningful null
branch. Examples:

    $this->observer?->executing($cmd);
    $this->execute($cmd);
    $this->observer?->completed($cmd);

    // or
    $this->logger?->info('start');
    $this->logger?->info('done');

The fix is a Null Object default so the consumer stops asking
"is it null?" at every step.

Pattern B does NOT fire for value-object-style nullables
(`DateTimeImmutable | null`, `BackedEnum | null`) — there null is
a genuine optional value, not a stand-in for "no behavior".

Configure replacements per type via the `null_objects` map:

    Backend\PreferNullObjectDefaultsProphet::class => [
        'null_objects' => [
            'callable'                       => App\Support\NullCallable::class,
            Psr\Log\LoggerInterface::class   => Psr\Log\NullLogger::class,
        ],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindNullObjectDefaultsCandidates)
            ->withNullObjectMap($this->resolveNullObjectMap());

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(ExtractClass::class)
            ->pipe($pipe)
            ->partitionMatches($this->translate(...))
            ->judge();
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

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $fixes = $this->collectFixes($ast);

        if ($fixes === []) {
            return RepentanceResult::unchanged();
        }

        $newContent = $this->applyFixes($content, $fixes);

        if ($newContent === $content) {
            return RepentanceResult::unchanged();
        }

        $penance = array_map(
            fn (array $fix) => "Hoisted default for \${$fix['param_name']} on {$fix['method']}()",
            $fixes,
        );

        return RepentanceResult::absolved($newContent, $penance);
    }

    /**
     * @return array<string, string>
     */
    private function resolveNullObjectMap(): array
    {
        $map = $this->config('null_objects', []);

        return is_array($map) ? $map : [];
    }

    private function translate(MatchResult $match): Sin|Warning|null
    {
        return match ($match->name) {
            'pattern_a1', 'pattern_a2_known' => $this->sinAt(
                line: $match->line,
                message: $this->messageForSin($match),
                snippet: $match->content,
                suggestion: $this->suggestionForSin($match),
            ),
            'pattern_a2_unknown' => $this->sinAt(
                line: $match->line,
                message: $this->messageForSin($match),
                snippet: $match->content,
                suggestion: $this->suggestionForSin($match),
            ),
            'pattern_b' => $this->warningAt(
                line: $match->line,
                message: $this->messageForWarning($match),
                snippet: $match->content,
            ),
            default => null,
        };
    }

    private function messageForSin(MatchResult $match): string
    {
        $groups = $match->groups;
        $marker = $match->name === 'pattern_a1' || $match->name === 'pattern_a2_known'
            ? '[AUTO-FIXABLE] '
            : '';

        return sprintf(
            '%s%s on %s(): `%s | null` is normalized in the body — hoist the default to the signature.',
            $marker,
            $groups['subject'],
            $groups['method'],
            $groups['type_name'],
        );
    }

    private function suggestionForSin(MatchResult $match): string
    {
        $groups = $match->groups;

        return match ($match->name) {
            'pattern_a1' => sprintf(
                'Replace `%s | null %s = null` with `%s %s = %s` and drop the normalization.',
                $groups['type_name'],
                $groups['subject'],
                $groups['type_name'],
                $groups['subject'],
                $groups['rhs_source'],
            ),
            'pattern_a2_known' => sprintf(
                'Replace `%s | null %s = null` with `%s %s = new %s` and drop the normalization.',
                $groups['type_name'],
                $groups['subject'],
                $groups['type_name'],
                $groups['subject'],
                $this->shortClassName($groups['null_object_fqcn']),
            ),
            'pattern_a2_unknown' => sprintf(
                'PHP forbids closure literals as parameter defaults. Introduce a Null Object class for `%s` and add it to the `null_objects` config map.',
                $groups['type_name'],
            ),
            default => '',
        };
    }

    private function messageForWarning(MatchResult $match): string
    {
        $groups = $match->groups;

        if ($groups['null_object_fqcn'] !== '') {
            return sprintf(
                '%s (%s | null) accessed via `?->` %s times in %s() — consider `new %s` as the default.',
                $groups['subject'],
                $groups['type_name'],
                $groups['access_count'],
                $groups['method'],
                $this->shortClassName($groups['null_object_fqcn']),
            );
        }

        return sprintf(
            '%s (%s | null) accessed via `?->` %s times in %s() — consider a Null Object default.',
            $groups['subject'],
            $groups['type_name'],
            $groups['access_count'],
            $groups['method'],
        );
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    /**
     * Walk the AST once and gather every Pattern A1 / A2-known param to
     * rewrite. Each fix carries the param node, the normalization
     * statement, and the chosen replacement default expression.
     *
     * @return array<array{
     *     param: Node\Param,
     *     normalizationStmt: Stmt,
     *     replacementDefault: string,
     *     newType: string,
     *     param_name: string,
     *     method: string
     * }>
     */
    private function collectFixes(array $ast): array
    {
        $fixes = [];
        $map = $this->resolveNullObjectMap();
        $finder = new NodeFinder;
        $printer = new PrettyPrinter\Standard;

        foreach ($finder->findInstanceOf($ast, Stmt\ClassMethod::class) as $method) {
            assert($method instanceof Stmt\ClassMethod);

            if ($method->stmts === null) {
                continue;
            }

            if ($this->methodUsesReflectionTricks($method)) {
                continue;
            }

            $normalizations = $this->collectLeadingNormalizations($method->stmts);

            if ($normalizations === []) {
                continue;
            }

            $assignmentsByName = $this->collectAssignmentsByName($method->stmts);

            foreach ($method->params as $param) {
                if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $name = $param->var->name;

                if (! isset($normalizations[$name])) {
                    continue;
                }

                $typeInfo = $this->extractFixableTypeInfo($param->type);

                if ($typeInfo === null) {
                    continue;
                }

                if (! $param->default instanceof Expr\ConstFetch
                    || strtolower($param->default->name->toString()) !== 'null'
                ) {
                    continue;
                }

                if ($this->paramReassignedToNull($name, $assignmentsByName, $normalizations[$name]['stmt'])) {
                    continue;
                }

                $rhs = $normalizations[$name]['rhs'];
                $replacement = $this->resolveReplacement($rhs, $typeInfo['typeName'], $map, $printer);

                if ($replacement === null) {
                    continue;
                }

                $fixes[] = [
                    'param' => $param,
                    'normalizationStmt' => $normalizations[$name]['stmt'],
                    'replacementDefault' => $replacement,
                    'newType' => $typeInfo['typeSource'],
                    'param_name' => $name,
                    'method' => $method->name->toString(),
                ];
            }
        }

        return $fixes;
    }

    /**
     * Apply collected fixes to the source string. Edits are sorted by
     * start offset descending so earlier edits don't shift the offsets
     * of later ones.
     *
     * @param  array<array{param: Node\Param, normalizationStmt: Stmt, replacementDefault: string, newType: string, param_name: string, method: string}>  $fixes
     */
    private function applyFixes(string $content, array $fixes): string
    {
        $edits = [];

        foreach ($fixes as $fix) {
            $param = $fix['param'];

            $typeStart = $param->type !== null ? $param->type->getStartFilePos() : null;
            $typeEnd = $param->type !== null ? $param->type->getEndFilePos() : null;

            if ($typeStart === null || $typeEnd === null) {
                continue;
            }

            $defaultStart = $param->default !== null ? $param->default->getStartFilePos() : null;
            $defaultEnd = $param->default !== null ? $param->default->getEndFilePos() : null;

            if ($defaultStart === null || $defaultEnd === null) {
                continue;
            }

            $normStart = $fix['normalizationStmt']->getStartFilePos();
            $normEnd = $fix['normalizationStmt']->getEndFilePos();

            $edits[] = ['start' => $typeStart, 'end' => $typeEnd + 1, 'replacement' => $fix['newType']];
            $edits[] = ['start' => $defaultStart, 'end' => $defaultEnd + 1, 'replacement' => $fix['replacementDefault']];
            $edits[] = $this->normalizationRemovalEdit($content, $normStart, $normEnd);
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start'])
                . $edit['replacement']
                . substr($content, $edit['end']);
        }

        return $content;
    }

    /**
     * Build an edit that erases a normalization statement plus its
     * trailing newline and leading indentation, so the resulting source
     * doesn't leave a blank line behind.
     *
     * @return array{start: int, end: int, replacement: string}
     */
    private function normalizationRemovalEdit(string $content, int $start, int $end): array
    {
        $lineStart = $start;

        while ($lineStart > 0 && $content[$lineStart - 1] !== "\n") {
            $ch = $content[$lineStart - 1];

            if ($ch === ' ' || $ch === "\t") {
                $lineStart--;

                continue;
            }

            $lineStart = $start;
            break;
        }

        $cut = $end + 1;

        if (isset($content[$cut]) && $content[$cut] === "\n") {
            $cut++;
        }

        return ['start' => $lineStart, 'end' => $cut, 'replacement' => ''];
    }

    /**
     * Decide the replacement default expression as PHP source. Returns
     * null when the RHS is not safe to inline (A3 runtime call, A2
     * closure without a configured Null Object).
     *
     * @param  array<string, string>  $nullObjectMap
     */
    private function resolveReplacement(Expr $rhs, string $paramTypeName, array $nullObjectMap, PrettyPrinter\Standard $printer): ?string
    {
        if ($this->isConstantDefaultExpression($rhs)) {
            return $printer->prettyPrintExpr($rhs);
        }

        if ($rhs instanceof Expr\Closure || $rhs instanceof Expr\ArrowFunction) {
            $mapKey = $this->resolveMapKey($paramTypeName, $nullObjectMap);

            if ($mapKey === null) {
                return null;
            }

            return 'new ' . $this->shortClassName($nullObjectMap[$mapKey]);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolveMapKey(string $paramTypeName, array $map): ?string
    {
        if (isset($map[$paramTypeName])) {
            return $paramTypeName;
        }

        $lower = strtolower($paramTypeName);

        foreach ($map as $key => $_) {
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Like the pipe's type extraction, but also returns the printable
     * non-null type source so the auto-fix can write it back into the
     * signature.
     *
     * @return array{typeName: string, typeSource: string}|null
     */
    private function extractFixableTypeInfo(?Node $type): ?array
    {
        if ($type instanceof Node\NullableType) {
            $inner = $type->type;

            if ($inner instanceof Node\Identifier) {
                return ['typeName' => $inner->toString(), 'typeSource' => $inner->toString()];
            }

            if ($inner instanceof Node\Name) {
                return ['typeName' => $inner->toString(), 'typeSource' => $inner->toString()];
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
                return ['typeName' => $only->toString(), 'typeSource' => $only->toString()];
            }

            if ($only instanceof Node\Name) {
                return ['typeName' => $only->toString(), 'typeSource' => $only->toString()];
            }

            return null;
        }

        return null;
    }

    /**
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
     * @return array{0: string, 1: Expr}|null
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

            if ($cond instanceof Expr\BinaryOp\Identical || $cond instanceof Expr\BinaryOp\Equal) {
                $paramName = $this->paramNameInNullCheck($cond->left, $cond->right);
            }

            if ($paramName === null) {
                return null;
            }

            $body = $stmt->stmts[0];

            if (! $body instanceof Stmt\Expression
                || ! $body->expr instanceof Expr\Assign
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
        $line = $normalizationStmt->getStartLine();

        foreach ($assignmentsByName[$name] ?? [] as $assign) {
            if ($assign->getStartLine() <= $line) {
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

    private function isConstantDefaultExpression(Expr $expr): bool
    {
        if ($expr instanceof Node\Scalar
            || $expr instanceof Expr\ConstFetch
            || $expr instanceof Expr\ClassConstFetch
        ) {
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

    private function methodUsesReflectionTricks(Stmt\ClassMethod $method): bool
    {
        $finder = new NodeFinder;

        $calls = $finder->find($method->stmts ?? [], static function (Node $n) {
            if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name) {
                return in_array(strtolower($n->name->toString()), ['func_get_args', 'func_num_args', 'func_get_arg'], true);
            }

            return false;
        });

        return ! empty($calls);
    }
}
