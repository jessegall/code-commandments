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
final class FindRawLiterals implements Pipe
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

    /**
     * Named type-helper values that, when compared against, have an equivalent
     * predicate. key = `T_Class::CONST` or `T_Class::factory()`; value =
     * [class, predicate, inverse-predicate or null].
     */
    private const HELPER_VALUE_MAP = [
        'T_String::EMPTY' => ['T_String', 'isEmpty', 'isNotEmpty'],
        'T_String::empty()' => ['T_String', 'isEmpty', 'isNotEmpty'],
        'T_Array::EMPTY' => ['T_Array', 'isEmpty', 'isNotEmpty'],
        'T_Array::empty()' => ['T_Array', 'isEmpty', 'isNotEmpty'],
        'T_Json::EMPTY_OBJECT' => ['T_Json', 'isEmptyObject', null],
        'T_Json::emptyObject()' => ['T_Json', 'isEmptyObject', null],
        'T_Json::EMPTY_ARRAY' => ['T_Json', 'isEmptyArray', null],
        'T_Json::emptyArray()' => ['T_Json', 'isEmptyArray', null],
        'T_Int::ZERO' => ['T_Int', 'isZero', 'isNotZero'],
        'T_Float::ZERO' => ['T_Float', 'isZero', 'isNotZero'],
        'T_Bool::TRUE' => ['T_Bool', 'isTrue', 'isFalse'],
        'T_Bool::FALSE' => ['T_Bool', 'isFalse', 'isTrue'],
    ];

    /**
     * Which literal categories to flag. `whitespace` (and the always-on empty
     * string / JSON / matrix) are on by default; the noisy categories are
     * opt-in.
     *
     * @var array{empty_array: bool, whitespace: bool, space: bool, separators: bool, sentinel_ints: bool, sentinel_floats: bool}
     */
    private array $options = [
        'empty_array' => false,
        'whitespace' => true,
        'space' => false,
        'separators' => false,
        'sentinel_ints' => false,
        'sentinel_floats' => false,
    ];

    /**
     * @param  array<string, bool>  $options
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, array_intersect_key($options, $this->options));

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $findings = self::analyze($input->ast, $input->content, $this->options);

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
                    'helper_class' => $finding['helper_class'],
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
    public static function analyze(array $ast, string $content, array $options): array
    {
        $options = array_merge([
            'empty_array' => false,
            'whitespace' => true,
            'space' => false,
            'separators' => false,
            'sentinel_ints' => false,
            'sentinel_floats' => false,
        ], $options);

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

            $finding = self::classifyStringLiteral($string, $content, $parents, $options);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        // Pass 3 — the nested-array seed `[[]]`. Always on: it is a
        // distinctive, low-noise literal (a stack/grid started with one empty
        // inner array). Marks the inner `[]` consumed so it is not separately
        // rewritten.
        foreach ($nodeFinder->findInstanceOf($ast, Expr\Array_::class) as $array) {
            if (isset($consumed[spl_object_id($array)]) || ! self::isMatrixSeed($array)) {
                continue;
            }

            $inner = $array->items[0]->value;

            if ($inner instanceof Expr\Array_) {
                $consumed[spl_object_id($inner)] = true;
            }

            $findings[] = self::finding(
                kind: 'matrix_literal',
                node: $array,
                content: $content,
                position: self::isConstPosition($array, $parents) ? 'const' : 'value',
                literal: self::source($content, $array),
            );
        }

        // Pass 4 — bare empty array literals `[]`, opt-in.
        if ($options['empty_array']) {
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

        // Pass 5 — sentinel integers 0 / 1 / -1, opt-in. `-1` is a UnaryMinus
        // wrapping `1`; flag it whole and consume the inner `1`.
        if ($options['sentinel_ints']) {
            foreach ($nodeFinder->findInstanceOf($ast, Expr\UnaryMinus::class) as $neg) {
                if (! $neg->expr instanceof Scalar\Int_ || $neg->expr->value !== 1 || self::inDeclare($neg, $parents)) {
                    continue;
                }

                $consumed[spl_object_id($neg->expr)] = true;
                $findings[] = self::finding('int_minus_one', $neg, $content, 'value', '-1');
            }

            foreach ($nodeFinder->findInstanceOf($ast, Scalar\Int_::class) as $int) {
                if (isset($consumed[spl_object_id($int)]) || ! in_array($int->value, [0, 1], true) || self::inDeclare($int, $parents)) {
                    continue;
                }

                $findings[] = self::finding(
                    kind: $int->value === 0 ? 'int_zero' : 'int_one',
                    node: $int,
                    content: $content,
                    position: 'value',
                    literal: (string) $int->value,
                );
            }
        }

        // Pass 6 — the float zero `0.0`, opt-in.
        if ($options['sentinel_floats']) {
            foreach ($nodeFinder->findInstanceOf($ast, Scalar\Float_::class) as $float) {
                if (isset($consumed[spl_object_id($float)]) || $float->value !== 0.0 || self::inDeclare($float, $parents)) {
                    continue;
                }

                $findings[] = self::finding(
                    kind: 'float_zero',
                    node: $float,
                    content: $content,
                    position: 'value',
                    literal: self::source($content, $float),
                );
            }
        }

        // Pass 7 — null-coalesce to a type empty:
        //   (string)($x ?? '')        -> T_String::coalesce($x)
        //   (int)($x ?? 0)            -> T_Int::coalesce($x)
        //   $x ?? T_Array::empty()    -> T_Array::coalesce($x)
        $castTypes = [
            Expr\Cast\String_::class => 'T_String',
            Expr\Cast\Int_::class => 'T_Int',
            Expr\Cast\Double::class => 'T_Float',
            Expr\Cast\Bool_::class => 'T_Bool',
        ];

        foreach ($nodeFinder->findInstanceOf($ast, Expr\Cast::class) as $cast) {
            $type = $castTypes[$cast::class] ?? null;

            if ($type === null || ! $cast->expr instanceof Expr\BinaryOp\Coalesce) {
                continue;
            }

            if (self::coalesceEmptyMatches($cast->expr->right, $type)) {
                $findings[] = self::coalesceFinding($cast, $cast->expr->left, $type, $content);
            }
        }

        foreach ($nodeFinder->findInstanceOf($ast, Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if (self::coalesceEmptyMatches($coalesce->right, 'T_Array')) {
                $findings[] = self::coalesceFinding($coalesce, $coalesce->left, 'T_Array', $content);
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

        // Comparison against a named type-helper value — `$x === T_Array::empty()`,
        // `$x !== T_Int::ZERO`, … — has a ready-made predicate.
        if ($isEquality) {
            $helper = self::helperValueOperand($left) ?? self::helperValueOperand($right);

            if ($helper !== null) {
                [$node, $info] = $helper;
                $other = $node === $left ? $right : $left;
                [$class, $predicate, $inverse] = $info;

                if ($negated && $inverse !== null) {
                    $predicate = $inverse;
                    $negated = false;
                }

                $consumed[spl_object_id($node)] = true;

                return self::comparisonFinding(
                    kind: 'helper_compare',
                    cmp: $cmp,
                    content: $content,
                    predicate: $predicate,
                    var: self::source($content, $other),
                    literal: self::source($content, $node),
                    negate: $negated,
                    helperClass: $class,
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
    private static function classifyStringLiteral(Scalar\String_ $string, string $content, array $parents, array $options): ?array
    {
        $position = self::isConstPosition($string, $parents) ? 'const' : 'value';
        $value = $string->value;

        $make = static fn (string $kind): array => self::finding(
            $kind,
            $string,
            $content,
            $position,
            self::source($content, $string),
        );

        // Always on — empty string and the JSON literals.
        $kind = match ($value) {
            '' => 'string_literal',
            '{}' => 'json_object_literal',
            '[]' => 'json_array_literal',
            default => null,
        };

        if ($kind === null && $options['whitespace']) {
            $kind = match ($value) {
                "\n" => 'newline',
                "\n\n" => 'paragraph',
                "\t" => 'tab',
                "\r" => 'carriage_return',
                "\r\n" => 'crlf',
                "\0" => 'null_byte',
                default => null,
            };
        }

        if ($kind === null && $options['space'] && $value === ' ') {
            $kind = 'space';
        }

        if ($kind === null && $options['separators']) {
            $kind = match ($value) {
                ',' => 'comma',
                ', ' => 'comma_space',
                '/' => 'slash',
                '.' => 'dot',
                '-' => 'dash',
                default => null,
            };
        }

        return $kind === null ? null : $make($kind);
    }

    /**
     * Whether the node sits inside a `declare(...)` directive, where a literal
     * is required and a class constant is illegal.
     *
     * @param  array<int, Node>  $parents
     */
    private static function inDeclare(Node $node, array $parents): bool
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\Declare_) {
                return true;
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return false;
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
            'helper_class' => '',
            'fixable' => true,
        ];
    }

    /**
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, helper_class: string, fixable: bool}
     */
    private static function coalesceFinding(Node $expr, Node $left, string $type, string $content): array
    {
        $leftSource = self::source($content, $left);

        // `??` has isset semantics — it suppresses undefined-key / unset-property
        // warnings on the left operand. Passing that operand to coalesce()
        // evaluates it eagerly, which would throw for, say, a missing array key.
        // So only a definitely-evaluable operand (a plain variable or $this->prop)
        // becomes the clean `coalesce($x)`; anything else keeps the suppression
        // with `coalesce($x ?? null)` so behaviour is preserved exactly.
        $var = self::isSafeCoalesceTarget($left) ? $leftSource : $leftSource . ' ?? null';

        return [
            'kind' => 'coalesce',
            'start' => (int) $expr->getStartFilePos(),
            'end' => (int) $expr->getEndFilePos(),
            'line' => $expr->getStartLine(),
            'position' => 'value',
            'predicate' => '',
            'negate' => false,
            'var' => $var,
            'literal' => self::source($content, $expr),
            'helper_class' => $type,
            'fixable' => true,
        ];
    }

    /**
     * Whether the operand can be evaluated eagerly without `??`'s isset
     * suppression changing behaviour.
     */
    private static function isSafeCoalesceTarget(Node $left): bool
    {
        if ($left instanceof Expr\Variable) {
            return true;
        }

        return $left instanceof Expr\PropertyFetch
            && $left->var instanceof Expr\Variable
            && $left->var->name === 'this'
            && $left->name instanceof Node\Identifier;
    }

    private static function coalesceEmptyMatches(Node $node, string $type): bool
    {
        if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Node\Name) {
            return $node->class->getLast() === $type;
        }

        if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), ['empty', 'zero'], true)
        ) {
            return $node->class->getLast() === $type;
        }

        return match ($type) {
            'T_String' => $node instanceof Scalar\String_ && $node->value === '',
            'T_Int' => $node instanceof Scalar\Int_ && $node->value === 0,
            'T_Float' => $node instanceof Scalar\Float_ && $node->value === 0.0,
            'T_Bool' => $node instanceof Expr\ConstFetch && strtolower($node->name->toString()) === 'false',
            default => false,
        };
    }

    /**
     * @return array{kind: string, start: int, end: int, line: int, position: string, predicate: string, negate: bool, var: string, literal: string, helper_class: string, fixable: bool}
     */
    private static function comparisonFinding(
        string $kind,
        Expr\BinaryOp $cmp,
        string $content,
        string $predicate,
        string $var,
        string $literal,
        bool $negate = false,
        string $helperClass = '',
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
            'helper_class' => $helperClass,
            'fixable' => true,
        ];
    }

    /**
     * Whether the array is a matrix seed — exactly one unkeyed, non-spread
     * element that is an empty inner array. Both the raw `[[]]` literal and
     * the already-half-converted `[T_Array::EMPTY]` / `[T_Array::empty()]`
     * forms count, so a partial earlier fix still collapses to MATRIX.
     */
    private static function isMatrixSeed(Expr\Array_ $array): bool
    {
        if (count($array->items) !== 1) {
            return false;
        }

        $item = $array->items[0];

        if (! $item instanceof Node\ArrayItem || $item->key !== null || $item->unpack) {
            return false;
        }

        $value = $item->value;

        if ($value instanceof Expr\Array_) {
            return $value->items === [];
        }

        if ($value instanceof Expr\ClassConstFetch) {
            return self::isArrayHelper($value->class)
                && $value->name instanceof Node\Identifier
                && $value->name->toString() === 'EMPTY';
        }

        if ($value instanceof Expr\StaticCall) {
            return self::isArrayHelper($value->class)
                && $value->name instanceof Node\Identifier
                && $value->name->toString() === 'empty'
                && $value->args === [];
        }

        return false;
    }

    private static function isArrayHelper(Node $class): bool
    {
        return $class instanceof Node\Name && $class->getLast() === 'T_Array';
    }

    private static function emptyStringNode(Node $node): ?Scalar\String_
    {
        return $node instanceof Scalar\String_ && $node->value === '' ? $node : null;
    }

    private static function jsonNode(Node $node): ?Scalar\String_
    {
        return $node instanceof Scalar\String_ && in_array($node->value, ['{}', '[]'], true) ? $node : null;
    }

    /**
     * If the node is a named type-helper value (`T_Array::EMPTY`,
     * `T_Int::ZERO`, `T_Array::empty()`, …), return [node, [class, predicate,
     * inverse]]; otherwise null.
     *
     * @return array{0: Node, 1: array{0: string, 1: string, 2: ?string}}|null
     */
    private static function helperValueOperand(Node $node): ?array
    {
        if ($node instanceof Expr\ClassConstFetch
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
        ) {
            $info = self::HELPER_VALUE_MAP[$node->class->getLast() . '::' . $node->name->toString()] ?? null;

            return $info === null ? null : [$node, $info];
        }

        if ($node instanceof Expr\StaticCall
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && $node->args === []
        ) {
            $info = self::HELPER_VALUE_MAP[$node->class->getLast() . '::' . $node->name->toString() . '()'] ?? null;

            return $info === null ? null : [$node, $info];
        }

        return null;
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
