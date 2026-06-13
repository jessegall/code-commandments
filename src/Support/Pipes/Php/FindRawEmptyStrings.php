<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Find raw empty literals and empty checks that should travel through named
 * type helpers (`T_String`, `T_Json`, `T_Array`) instead of bare `''`, `'{}'`,
 * `'[]'`, `[]`, and `=== ''` / `strlen()` / `trim()` checks.
 *
 * The detection lives in {@see analyze()} so the judge pipe and the prophet's
 * auto-fixer share one classification and can never disagree.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindRawEmptyStrings implements Pipe
{
    /**
     * `strlen`-like calls whose comparison to 0/1 is an empty-string check.
     */
    private const LENGTH_FUNCTIONS = ['strlen', 'mb_strlen'];

    /**
     * `trim`-like calls that bake a normalisation decision into a `=== ''`.
     */
    private const TRIM_FUNCTIONS = ['trim', 'ltrim', 'rtrim', 'mb_trim'];

    /**
     * Short names of the type helpers themselves — files defining them hold
     * the one true `''` and must not be flagged.
     */
    private const TYPE_CLASS_NAMES = ['T_String', 'T_Json', 'T_Array', 'T_Int', 'T_Float', 'T_Bool', 'T_Null'];

    private bool $flagEmptyArray = false;

    public function withFlagEmptyArray(bool $flag): self
    {
        $this->flagEmptyArray = $flag;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $findings = self::analyze($input->ast, $input->content, $this->flagEmptyArray);

        $matches = [];

        foreach ($findings as $finding) {
            $matches[] = new MatchResult(
                name: $finding['kind'],
                pattern: '',
                match: $finding['literal'],
                line: $finding['line'],
                offset: null,
                content: $this->snippet($input->content, $finding['line']),
                groups: [
                    'kind' => $finding['kind'],
                    'position' => $finding['position'],
                    'predicate' => $finding['predicate'],
                    'negate' => $finding['negate'] ? '1' : '',
                    'var' => $finding['var'],
                    'literal' => $finding['literal'],
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * Classify every raw empty literal / empty check in the AST.
     *
     * @param  array<Node>  $ast
     * @return list<array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, fixable: bool}>
     */
    public static function analyze(array $ast, string $content, bool $flagEmptyArray): array
    {
        $nodeFinder = new NodeFinder;

        // Files that DEFINE a type helper hold the canonical literal — skip them.
        foreach ($nodeFinder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $classLike) {
            if ($classLike->name !== null && in_array($classLike->name->toString(), self::TYPE_CLASS_NAMES, true)) {
                return [];
            }
        }

        $parents = self::buildParentMap($ast);
        $findings = [];
        $consumed = [];

        // Pass 1 — comparisons. These consume their literal operands so the
        // literal pass does not double-report them.
        $comparisons = $nodeFinder->find($ast, static fn (Node $n): bool => self::isComparison($n));

        foreach ($comparisons as $cmp) {
            /** @var Expr\BinaryOp $cmp */
            $finding = self::classifyComparison($cmp, $content, $consumed);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        // Pass 2 — bare literals not already consumed by a comparison.
        foreach ($nodeFinder->findInstanceOf($ast, Scalar\String_::class) as $string) {
            if (isset($consumed[spl_object_id($string)])) {
                continue;
            }

            $finding = self::classifyStringLiteral($string, $content, $parents);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        // Pass 3 — empty array literals, opt-in.
        if ($flagEmptyArray) {
            foreach ($nodeFinder->findInstanceOf($ast, Expr\Array_::class) as $array) {
                if ($array->items !== [] || isset($consumed[spl_object_id($array)])) {
                    continue;
                }

                $findings[] = self::finding(
                    kind: 'array_literal',
                    node: $array,
                    content: $content,
                    position: self::isConstPosition($array, $parents) ? 'const' : 'value',
                    literal: '[]',
                );
            }
        }

        usort($findings, static fn ($a, $b) => ($a['start'] <=> $b['start']) ?: ($b['end'] <=> $a['end']));

        // Drop findings nested inside another (e.g. the `''` inside
        // `trim('') === ''`) so an auto-fix never rewrites overlapping ranges.
        $result = [];
        $lastEnd = -1;

        foreach ($findings as $finding) {
            if ($finding['start'] <= $lastEnd) {
                continue;
            }

            $result[] = $finding;
            $lastEnd = $finding['end'];
        }

        return $result;
    }

    /**
     * @param  array<int, true>  $consumed
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, fixable: bool}|null
     */
    private static function classifyComparison(Expr\BinaryOp $cmp, string $content, array &$consumed): ?array
    {
        $left = $cmp->left;
        $right = $cmp->right;

        $isEquality = $cmp instanceof Expr\BinaryOp\Identical
            || $cmp instanceof Expr\BinaryOp\NotIdentical
            || $cmp instanceof Expr\BinaryOp\Equal
            || $cmp instanceof Expr\BinaryOp\NotEqual;

        $negated = $cmp instanceof Expr\BinaryOp\NotIdentical || $cmp instanceof Expr\BinaryOp\NotEqual;

        // Empty-string / JSON literal on one side.
        if ($isEquality) {
            $emptyOperand = self::emptyStringNode($left) ?? self::emptyStringNode($right);
            $jsonOperand = self::jsonNode($left) ?? self::jsonNode($right);

            if ($emptyOperand !== null) {
                $other = $emptyOperand === $left ? $right : $left;

                if (self::emptyStringNode($other) !== null) {
                    return null; // '' === '' — nothing to suggest.
                }

                $consumed[spl_object_id($emptyOperand)] = true;

                // `trim($x) === ''` is a blank check — rewrite to isBlank($x)
                // with the INNER argument, not isEmpty(trim($x)). Only the
                // single-argument form is whitespace trimming; `trim($x, '/')`
                // strips something else and stays a plain emptiness check.
                $isBlank = $other instanceof Expr\FuncCall
                    && $other->name instanceof Node\Name
                    && in_array($other->name->toString(), self::TRIM_FUNCTIONS, true)
                    && count($other->args) === 1
                    && $other->args[0] instanceof Node\Arg;

                if ($isBlank) {
                    /** @var Expr\FuncCall $other */
                    return self::comparisonFinding(
                        kind: 'trim_compare',
                        cmp: $cmp,
                        content: $content,
                        predicate: $negated ? 'isNotBlank' : 'isBlank',
                        var: self::source($content, $other->args[0]->value),
                        literal: self::source($content, $emptyOperand),
                    );
                }

                return self::comparisonFinding(
                    kind: 'string_compare',
                    cmp: $cmp,
                    content: $content,
                    predicate: $negated ? 'isNotEmpty' : 'isEmpty',
                    var: self::source($content, $other),
                    literal: self::source($content, $emptyOperand),
                );
            }

            if ($jsonOperand !== null) {
                $other = $jsonOperand === $left ? $right : $left;
                $consumed[spl_object_id($jsonOperand)] = true;

                $isObject = $jsonOperand->value === '{}';

                return self::comparisonFinding(
                    kind: $isObject ? 'json_object_compare' : 'json_array_compare',
                    cmp: $cmp,
                    content: $content,
                    predicate: $isObject ? 'isEmptyObject' : 'isEmptyArray',
                    var: self::source($content, $other),
                    literal: self::source($content, $jsonOperand),
                    negate: $negated,
                );
            }
        }

        // strlen($x) compared to 0 / 1.
        $lenCall = self::lengthCall($left) ?? self::lengthCall($right);

        if ($lenCall !== null) {
            $intNode = self::lengthCall($left) !== null ? $right : $left;
            $intValue = $intNode instanceof Scalar\Int_ ? $intNode->value : null;

            if ($intValue === null || ! isset($lenCall->args[0]) || ! $lenCall->args[0] instanceof Node\Arg) {
                return null;
            }

            $predicate = self::lengthPredicate($cmp, self::lengthCall($left) !== null, $intValue);

            if ($predicate === null) {
                return null;
            }

            return self::comparisonFinding(
                kind: 'strlen_compare',
                cmp: $cmp,
                content: $content,
                predicate: $predicate,
                var: self::source($content, $lenCall->args[0]->value),
                literal: self::source($content, $lenCall),
            );
        }

        return null;
    }

    /**
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, fixable: bool}|null
     */
    private static function classifyStringLiteral(Scalar\String_ $string, string $content, array $parents): ?array
    {
        $position = self::isConstPosition($string, $parents) ? 'const' : 'value';

        if ($string->value === '') {
            return self::finding('string_literal', $string, $content, $position, self::source($content, $string));
        }

        if ($string->value === '{}') {
            return self::finding('json_object_literal', $string, $content, $position, self::source($content, $string));
        }

        if ($string->value === '[]') {
            return self::finding('json_array_literal', $string, $content, $position, self::source($content, $string));
        }

        return null;
    }

    /**
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, fixable: bool}
     */
    private static function finding(string $kind, Node $node, string $content, string $position, string $literal): array
    {
        return [
            'kind' => $kind,
            'start' => (int) $node->getStartFilePos(),
            'end' => (int) $node->getEndFilePos(),
            'line' => $node->getStartLine(),
            'position' => $position,
            'predicate' => '',
            'negate' => false,
            'var' => '',
            'literal' => $literal,
            'fixable' => true,
        ];
    }

    /**
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, fixable: bool}
     */
    private static function comparisonFinding(
        string $kind,
        Expr\BinaryOp $cmp,
        string $content,
        string $predicate,
        string $var,
        string $literal,
        bool $negate = false,
    ): array {
        return [
            'kind' => $kind,
            'start' => (int) $cmp->getStartFilePos(),
            'end' => (int) $cmp->getEndFilePos(),
            'line' => $cmp->getStartLine(),
            'position' => 'value',
            'predicate' => $predicate,
            'negate' => $negate,
            'var' => $var,
            'literal' => $literal,
            'fixable' => true,
        ];
    }

    private static function emptyStringNode(Node $node): ?Scalar\String_
    {
        return $node instanceof Scalar\String_ && $node->value === '' ? $node : null;
    }

    private static function jsonNode(Node $node): ?Scalar\String_
    {
        return $node instanceof Scalar\String_ && in_array($node->value, ['{}', '[]'], true) ? $node : null;
    }

    private static function lengthCall(Node $node): ?Expr\FuncCall
    {
        return $node instanceof Expr\FuncCall
            && $node->name instanceof Node\Name
            && in_array($node->name->toString(), self::LENGTH_FUNCTIONS, true)
            ? $node
            : null;
    }

    /**
     * Map a `strlen() <op> <int>` comparison to an empty/non-empty predicate,
     * or null when it is not an emptiness check.
     */
    private static function lengthPredicate(Expr\BinaryOp $cmp, bool $lenOnLeft, int $value): ?string
    {
        $op = $cmp::class;

        // Normalise directional operators when the length call is on the right.
        if (! $lenOnLeft) {
            $op = match ($op) {
                Expr\BinaryOp\Greater::class => Expr\BinaryOp\Smaller::class,
                Expr\BinaryOp\GreaterOrEqual::class => Expr\BinaryOp\SmallerOrEqual::class,
                Expr\BinaryOp\Smaller::class => Expr\BinaryOp\Greater::class,
                Expr\BinaryOp\SmallerOrEqual::class => Expr\BinaryOp\GreaterOrEqual::class,
                default => $op,
            };
        }

        if ($value === 0) {
            return match ($op) {
                Expr\BinaryOp\Identical::class, Expr\BinaryOp\Equal::class, Expr\BinaryOp\SmallerOrEqual::class => 'isEmpty',
                Expr\BinaryOp\NotIdentical::class, Expr\BinaryOp\NotEqual::class, Expr\BinaryOp\Greater::class => 'isNotEmpty',
                default => null,
            };
        }

        if ($value === 1) {
            return match ($op) {
                Expr\BinaryOp\Smaller::class => 'isEmpty',
                Expr\BinaryOp\GreaterOrEqual::class => 'isNotEmpty',
                default => null,
            };
        }

        return null;
    }

    private static function isComparison(Node $node): bool
    {
        return $node instanceof Expr\BinaryOp\Identical
            || $node instanceof Expr\BinaryOp\NotIdentical
            || $node instanceof Expr\BinaryOp\Equal
            || $node instanceof Expr\BinaryOp\NotEqual
            || $node instanceof Expr\BinaryOp\Greater
            || $node instanceof Expr\BinaryOp\GreaterOrEqual
            || $node instanceof Expr\BinaryOp\Smaller
            || $node instanceof Expr\BinaryOp\SmallerOrEqual;
    }

    /**
     * Whether the literal sits in a constant-expression position (parameter
     * default, property/const value, enum case, attribute argument) where a
     * method call is illegal and the `::CONST` form is required.
     *
     * @param  array<int, Node>  $parents
     */
    public static function isConstPosition(Node $node, array $parents): bool
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Param
                || $current instanceof Node\Const_
                || $current instanceof Node\Attribute
                || $current instanceof Node\StaticVar
                || $current instanceof Node\Stmt\EnumCase
                || $current instanceof Node\Stmt\Property
                || $current instanceof Node\Stmt\ClassConst
            ) {
                return true;
            }

            if ($current instanceof Node\Stmt) {
                return false;
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return false;
    }

    private static function source(string $content, Node $node): string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start === null || $end === null || $start < 0 || $end < $start) {
            return '?';
        }

        return substr($content, $start, $end - $start + 1);
    }

    /**
     * @param  array<Node>  $ast
     * @return array<int, Node>
     */
    private static function buildParentMap(array $ast): array
    {
        $parents = [];

        $visitor = new class($parents) extends NodeVisitorAbstract {
            /** @var array<int, Node> */
            public array $parents;
            /** @var array<Node> */
            private array $stack = [];

            public function __construct(array &$parents)
            {
                $this->parents = &$parents;
            }

            public function enterNode(Node $node): ?int
            {
                $parent = end($this->stack);

                if ($parent !== false) {
                    $this->parents[spl_object_id($node)] = $parent;
                }

                $this->stack[] = $node;

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                array_pop($this->stack);

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->parents;
    }

    private function snippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
