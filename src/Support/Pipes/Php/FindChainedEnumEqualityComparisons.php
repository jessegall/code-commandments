<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * Find enum equality comparisons where a subject is compared against case(s)
 * of one enum — single comparisons and the boolean chains that string them
 * together.
 *
 *   $kind === Foo::A                                       // equals
 *   $kind !== Foo::A                                       // not_equals
 *   $kind === Foo::A || $kind === Foo::B                   // one_of   (>= 2 atoms)
 *   $status !== Status::Done && $status !== Status::Failed // not_one_of
 *
 * A chain is the maximal sub-tree of either `||` or `&&` operators. To count
 * it must:
 *   - contain only `===` (for `||`) or only `!==` (for `&&`),
 *   - share a single LHS expression across every atom (pretty-printed equality),
 *   - share a single enum class on every RHS,
 *   - have length >= minChain.
 *
 * When minChain <= 1 a second pass emits each standalone `===`/`!==` against an
 * enum-case fetch as a length-1 `equals`/`not_equals` match. Atoms already
 * consumed by an emitted chain are skipped, so a real chain never double-counts.
 *
 * The pipe stays away from wire-format scopes (`toArray`, `jsonSerialize`,
 * etc., and `JsonResource`/`Response` classes) — the same boundary other
 * enum-leaning prophets respect.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindChainedEnumEqualityComparisons implements Pipe
{
    private const WIRE_FORMAT_METHODS = [
        'toArray', 'jsonSerialize', 'render', 'toResponse', 'resolve',
    ];

    private const WIRE_FORMAT_PARENT_SUFFIXES = [
        'JsonResource', 'Resource', 'Response',
    ];

    private int $minChain = 1;

    /** @var list<string> Lowercased FQCNs or short names. */
    private array $excludeEnums = [];

    /** @var array{equals: string, not_equals: string} Configured singular method names. */
    private array $equalityMethods = ['equals' => 'equals', 'not_equals' => 'notEquals'];

    public function withMinChain(int $n): self
    {
        $this->minChain = max(1, $n);

        return $this;
    }

    /**
     * The configured names of the SINGULAR equals-family helpers, so the pipe
     * can recognise an existing static call (`Enum::equals($x, Enum::Case)`)
     * that should be re-anchored onto the literal case.
     */
    public function withEqualityMethods(string $equals, string $notEquals): self
    {
        $this->equalityMethods = ['equals' => $equals, 'not_equals' => $notEquals];

        return $this;
    }

    /**
     * @param  list<string>  $enums  Either short names or FQCNs (matched case-insensitively).
     */
    public function withExcludeEnums(array $enums): self
    {
        $this->excludeEnums = array_values(array_map(
            static fn (string $s) => strtolower(ltrim($s, '\\')),
            $enums,
        ));

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $printer = new PrettyPrinter\Standard;
        $finder = new NodeFinder;
        $parentMap = $this->buildParentMap($input->ast);

        $matches = [];

        // Object ids of atoms already consumed by an emitted chain match — they
        // must not also surface as standalone single comparisons.
        $consumed = [];

        $orRoots = $this->collectChainRoots(
            $finder->findInstanceOf($input->ast, Expr\BinaryOp\BooleanOr::class),
            Expr\BinaryOp\BooleanOr::class,
            $parentMap,
        );

        foreach ($orRoots as $root) {
            if ($this->isInsideWireFormatScope($root, $parentMap)) {
                continue;
            }

            $atoms = $this->flatten($root, Expr\BinaryOp\BooleanOr::class);

            if (count($atoms) < $this->minChain) {
                continue;
            }

            $analysis = $this->analyseChain($atoms, Expr\BinaryOp\Identical::class, $printer);

            if ($analysis === null) {
                continue;
            }

            $resolvedFqcn = $this->resolveFqcn($analysis['classNode'], $input->useStatements, $input->namespace);

            if ($this->isExcluded($resolvedFqcn, $analysis['shortName'])) {
                continue;
            }

            $matches[] = $this->makeMatch(
                root: $root,
                analysis: $analysis,
                resolvedFqcn: $resolvedFqcn,
                content: $input->content,
                op: 'one_of',
            );

            foreach ($analysis['atoms'] as $atom) {
                $consumed[spl_object_id($atom)] = true;
            }
        }

        $andRoots = $this->collectChainRoots(
            $finder->findInstanceOf($input->ast, Expr\BinaryOp\BooleanAnd::class),
            Expr\BinaryOp\BooleanAnd::class,
            $parentMap,
        );

        foreach ($andRoots as $root) {
            if ($this->isInsideWireFormatScope($root, $parentMap)) {
                continue;
            }

            $atoms = $this->flatten($root, Expr\BinaryOp\BooleanAnd::class);

            if (count($atoms) < $this->minChain) {
                continue;
            }

            $analysis = $this->analyseChain($atoms, Expr\BinaryOp\NotIdentical::class, $printer);

            if ($analysis === null) {
                continue;
            }

            $resolvedFqcn = $this->resolveFqcn($analysis['classNode'], $input->useStatements, $input->namespace);

            if ($this->isExcluded($resolvedFqcn, $analysis['shortName'])) {
                continue;
            }

            $matches[] = $this->makeMatch(
                root: $root,
                analysis: $analysis,
                resolvedFqcn: $resolvedFqcn,
                content: $input->content,
                op: 'not_one_of',
            );

            foreach ($analysis['atoms'] as $atom) {
                $consumed[spl_object_id($atom)] = true;
            }
        }

        if ($this->minChain <= 1) {
            $this->collectSingles($input, $finder, $printer, $parentMap, $consumed, $matches);
        }

        $this->collectInArray($input, $finder, $printer, $parentMap, $matches);
        $this->collectStaticEqualityCalls($input, $finder, $printer, $parentMap, $matches);

        return $input->with(matches: $matches);
    }

    /**
     * `Enum::equals($x, Enum::Case)` / `Enum::notEquals($x, Enum::Case)` — an
     * existing CompareSelf static call whose target is a LITERAL case. The
     * static form is only warranted when neither operand is a known case;
     * here it should be re-anchored onto the case as `Enum::Case->equals($x)`
     * (still null-safe, reads better). Calls with no literal case (two dynamic
     * values) or two literal cases are left alone.
     *
     * @param  array<int, Node>  $parentMap
     * @param  list<MatchResult>  $matches
     */
    private function collectStaticEqualityCalls(
        mixed $input,
        NodeFinder $finder,
        PrettyPrinter\Standard $printer,
        array $parentMap,
        array &$matches,
    ): void {
        $methodToOp = [];

        foreach ($this->equalityMethods as $op => $method) {
            $methodToOp[strtolower($method)] = $op;
        }

        foreach ($finder->findInstanceOf($input->ast, Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || ! $call->class instanceof Node\Name) {
                continue;
            }

            $op = $methodToOp[strtolower($call->name->toString())] ?? null;

            if ($op === null) {
                continue;
            }

            // A first-class callable (`->equals(...)`) carries no real args and
            // would assert on getArgs(); it is not a comparison to rewrite (#18).
            if ($call->isFirstClassCallable()) {
                continue;
            }

            $args = $call->getArgs();

            // The singular helpers take exactly (subject, case). Named or
            // spread args can't be re-ordered safely.
            if (count($args) !== 2 || $args[0]->name !== null || $args[1]->name !== null
                || $args[0]->unpack || $args[1]->unpack) {
                continue;
            }

            $left = $args[0]->value;
            $right = $args[1]->value;
            $leftIsCase = $this->isCaseFetch($left);
            $rightIsCase = $this->isCaseFetch($right);

            // Need exactly one literal case to anchor on.
            if ($leftIsCase === $rightIsCase) {
                continue;
            }

            /** @var Expr\ClassConstFetch $caseFetch */
            $caseFetch = $leftIsCase ? $left : $right;
            $subject = $leftIsCase ? $right : $left;

            if (! $caseFetch->class instanceof Node\Name || ! $caseFetch->name instanceof Node\Identifier) {
                continue;
            }

            $caseName = $caseFetch->name->toString();

            if ($caseName === '' || strtolower($caseName) === 'class') {
                continue;
            }

            if ($this->isInsideWireFormatScope($call, $parentMap)) {
                continue;
            }

            $classNode = $caseFetch->class;
            $shortName = $classNode->getLast();
            $resolvedFqcn = $this->resolveFqcn($classNode, $input->useStatements, $input->namespace);

            if ($this->isExcluded($resolvedFqcn, $shortName)) {
                continue;
            }

            $analysis = [
                'lhsSource' => $printer->prettyPrintExpr($subject),
                'classNode' => $classNode,
                'shortName' => $shortName,
                'cases' => [$caseName],
                'atoms' => [],
            ];

            $matches[] = $this->makeMatch(
                root: $call,
                analysis: $analysis,
                resolvedFqcn: $resolvedFqcn,
                content: $input->content,
                op: $op,
                fromStatic: true,
            );
        }
    }

    /**
     * `in_array($x, [Enum::A, Enum::B, …], true)` — a membership test against a
     * closed set of cases of ONE enum — is a one-of test and collapses to
     * `$x->equalsAny(...)` / `Enum::equalsAny($x, ...)`.
     *
     * @param  array<int, Node>  $parentMap
     * @param  list<MatchResult>  $matches
     */
    private function collectInArray(
        mixed $input,
        NodeFinder $finder,
        PrettyPrinter\Standard $printer,
        array $parentMap,
        array &$matches,
    ): void {
        foreach ($finder->findInstanceOf($input->ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name
                || strtolower($call->name->toString()) !== 'in_array'
                || count($call->args) < 2
            ) {
                continue;
            }

            $needle = $call->args[0] instanceof Node\Arg ? $call->args[0]->value : null;
            $haystack = $call->args[1] instanceof Node\Arg ? $call->args[1]->value : null;

            if ($needle === null || ! $haystack instanceof Expr\Array_) {
                continue;
            }

            $analysis = $this->analyseCaseArray($needle, $haystack, $printer);

            if ($analysis === null || $this->isInsideWireFormatScope($call, $parentMap)) {
                continue;
            }

            $resolvedFqcn = $this->resolveFqcn($analysis['classNode'], $input->useStatements, $input->namespace);

            if ($this->isExcluded($resolvedFqcn, $analysis['shortName'])) {
                continue;
            }

            $matches[] = $this->makeMatch(
                root: $call,
                analysis: $analysis,
                resolvedFqcn: $resolvedFqcn,
                content: $input->content,
                op: count($analysis['cases']) === 1 ? 'equals' : 'one_of',
            );
        }
    }

    /**
     * Whether the comparison is the SOLE condition of an `if` guard whose body
     * just bails out — `if ($x === Enum::Case) { continue; }`. PHPStan narrows
     * the enum through such a guard; `equals()` would not, breaking a later
     * exhaustive `match`. So these stay `===`.
     *
     * @param  array<int, Node>  $parentMap
     */
    private function isNarrowingGuard(Node $node, array $parentMap): bool
    {
        $parent = $parentMap[spl_object_id($node)] ?? null;

        if (! $parent instanceof Node\Stmt\If_ || $parent->cond !== $node
            || $parent->elseifs !== [] || $parent->else !== null
            || count($parent->stmts) !== 1
        ) {
            return false;
        }

        return $parent->stmts[0] instanceof Node\Stmt\Continue_
            || $parent->stmts[0] instanceof Node\Stmt\Return_
            || $parent->stmts[0] instanceof Node\Stmt\Throw_
            || $parent->stmts[0] instanceof Node\Stmt\Break_;
    }

    /**
     * Validate that every element of the array is an enum-case fetch of the same
     * enum, and return the chain analysis shape.
     *
     * @return array{lhsSource: string, classNode: Node\Name, shortName: string, cases: list<string>, atoms: list<Node>}|null
     */
    private function analyseCaseArray(Node $needle, Expr\Array_ $array, PrettyPrinter\Standard $printer): ?array
    {
        if ($array->items === []) {
            return null;
        }

        $classKey = null;
        $classNode = null;
        $shortName = null;
        $cases = [];

        foreach ($array->items as $item) {
            if (! $item instanceof Node\ArrayItem || $item->key !== null || $item->byRef || $item->unpack) {
                return null;
            }

            if (! $this->isCaseFetch($item->value)) {
                return null;
            }

            /** @var Expr\ClassConstFetch $fetch */
            $fetch = $item->value;

            if (! $fetch->class instanceof Node\Name || ! $fetch->name instanceof Node\Identifier) {
                return null;
            }

            $currentKey = $fetch->class->toString();

            if ($classKey === null) {
                $classKey = $currentKey;
                $classNode = $fetch->class;
                $shortName = $fetch->class->getLast();
            } elseif ($classKey !== $currentKey) {
                return null;
            }

            $cases[] = $fetch->name->toString();
        }

        if ($classNode === null || $shortName === null) {
            return null;
        }

        return [
            'lhsSource' => $printer->prettyPrintExpr($needle),
            'classNode' => $classNode,
            'shortName' => $shortName,
            'cases' => $cases,
            'atoms' => [],
        ];
    }

    /**
     * Single-comparison pass: every `===`/`!==` that compares a subject to an
     * enum-case fetch and is NOT already part of an emitted chain.
     *
     * @param  array<int, Node>  $parentMap
     * @param  array<int, bool>  $consumed
     * @param  list<MatchResult>  $matches
     */
    private function collectSingles(
        mixed $input,
        NodeFinder $finder,
        PrettyPrinter\Standard $printer,
        array $parentMap,
        array $consumed,
        array &$matches,
    ): void {
        $singles = [
            [Expr\BinaryOp\Identical::class, 'equals'],
            [Expr\BinaryOp\NotIdentical::class, 'not_equals'],
        ];

        foreach ($singles as [$nodeClass, $op]) {
            foreach ($finder->findInstanceOf($input->ast, $nodeClass) as $node) {
                if (isset($consumed[spl_object_id($node)])) {
                    continue;
                }

                if ($this->isInsideWireFormatScope($node, $parentMap)) {
                    continue;
                }

                // A `=== Enum::Case` (or `!==`) that is a load-bearing narrowing
                // guard — the sole condition of an `if` whose body just bails
                // (continue/return/throw/break) — must stay `===`: PHPStan
                // narrows the enum through `===` but NOT through the trait's
                // `equals()`, so converting it breaks a following exhaustive
                // match.
                if ($this->isNarrowingGuard($node, $parentMap)) {
                    continue;
                }

                /** @var Expr\BinaryOp $node */
                [$expr, $caseFetch] = $this->orientAtom($node);

                if ($caseFetch === null || ! $caseFetch->class instanceof Node\Name) {
                    continue;
                }

                if (! $caseFetch->name instanceof Node\Identifier) {
                    continue;
                }

                $caseName = $caseFetch->name->toString();

                if ($caseName === '' || strtolower($caseName) === 'class') {
                    continue;
                }

                $classNode = $caseFetch->class;
                $shortName = $classNode->getLast();
                $resolvedFqcn = $this->resolveFqcn($classNode, $input->useStatements, $input->namespace);

                if ($this->isExcluded($resolvedFqcn, $shortName)) {
                    continue;
                }

                $analysis = [
                    'lhsSource' => $printer->prettyPrintExpr($expr),
                    'classNode' => $classNode,
                    'shortName' => $shortName,
                    'cases' => [$caseName],
                    'atoms' => [$node],
                ];

                $matches[] = $this->makeMatch(
                    root: $node,
                    analysis: $analysis,
                    resolvedFqcn: $resolvedFqcn,
                    content: $input->content,
                    op: $op,
                );
            }
        }
    }

    /**
     * Keep only nodes whose direct parent is not the same kind — i.e., the
     * topmost node of each maximal `||` or `&&` chain.
     *
     * @template T of Node
     * @param  list<T>  $nodes
     * @param  class-string<T>  $kind
     * @param  array<int, Node>  $parentMap
     * @return list<T>
     */
    private function collectChainRoots(array $nodes, string $kind, array $parentMap): array
    {
        $roots = [];

        foreach ($nodes as $node) {
            $parent = $parentMap[spl_object_id($node)] ?? null;

            if ($parent instanceof $kind) {
                continue;
            }

            $roots[] = $node;
        }

        return $roots;
    }

    /**
     * Flatten a tree of $kind operators into the leaf atoms.
     *
     * @return list<Node>
     */
    private function flatten(Node $node, string $kind): array
    {
        if ($node instanceof $kind) {
            /** @var Expr\BinaryOp $node */
            return array_merge(
                $this->flatten($node->left, $kind),
                $this->flatten($node->right, $kind),
            );
        }

        return [$node];
    }

    /**
     * Validate that every atom is the expected comparison shape and that the
     * chain agrees on LHS expression + enum class.
     *
     * @param  list<Node>  $atoms
     * @return array{lhsSource: string, classNode: Node\Name, shortName: string, cases: list<string>, atoms: list<Node>}|null
     */
    private function analyseChain(array $atoms, string $comparisonClass, PrettyPrinter\Standard $printer): ?array
    {
        $lhsSource = null;
        $classKey = null;
        $classNode = null;
        $shortName = null;
        $cases = [];

        foreach ($atoms as $atom) {
            if (! $atom instanceof $comparisonClass) {
                return null;
            }

            /** @var Expr\BinaryOp $atom */
            [$expr, $caseFetch] = $this->orientAtom($atom);

            if ($caseFetch === null) {
                return null;
            }

            $printed = $printer->prettyPrintExpr($expr);

            if ($lhsSource === null) {
                $lhsSource = $printed;
            } elseif ($lhsSource !== $printed) {
                return null;
            }

            $caseClass = $caseFetch->class;

            if (! $caseClass instanceof Node\Name) {
                return null;
            }

            $currentKey = $caseClass->toString();

            if ($classKey === null) {
                $classKey = $currentKey;
                $classNode = $caseClass;
                $shortName = $caseClass->getLast();
            } elseif ($classKey !== $currentKey) {
                return null;
            }

            if (! $caseFetch->name instanceof Node\Identifier) {
                return null;
            }

            $caseName = $caseFetch->name->toString();

            if ($caseName === '' || strtolower($caseName) === 'class') {
                return null;
            }

            $cases[] = $caseName;
        }

        if ($lhsSource === null || $classNode === null || $shortName === null) {
            return null;
        }

        return [
            'lhsSource' => $lhsSource,
            'classNode' => $classNode,
            'shortName' => $shortName,
            'cases' => $cases,
            'atoms' => $atoms,
        ];
    }

    /**
     * Identify which side of a binary comparison is the enum-case fetch and
     * which is the candidate LHS expression. Returns [lhs, caseFetch] or
     * [null, null] when neither side matches.
     *
     * @return array{0: Node|null, 1: Expr\ClassConstFetch|null}
     */
    private function orientAtom(Expr\BinaryOp $atom): array
    {
        if ($this->isCaseFetch($atom->right)) {
            return [$atom->left, $atom->right];
        }

        if ($this->isCaseFetch($atom->left)) {
            return [$atom->right, $atom->left];
        }

        return [null, null];
    }

    private function isCaseFetch(Node $node): bool
    {
        if (! $node instanceof Expr\ClassConstFetch) {
            return false;
        }

        if (! $node->class instanceof Node\Name) {
            return false;
        }

        if (! $node->name instanceof Node\Identifier) {
            return false;
        }

        // `Foo::class` is not a case access.
        return strtolower($node->name->toString()) !== 'class';
    }

    /**
     * @param  array{lhsSource: string, classNode: Node\Name, shortName: string, cases: list<string>, atoms: list<Node>}  $analysis
     */
    private function makeMatch(
        Node $root,
        array $analysis,
        string $resolvedFqcn,
        string $content,
        string $op,
        bool $fromStatic = false,
    ): MatchResult {
        $line = $root->getStartLine();

        return new MatchResult(
            name: 'enum_equality_chain',
            pattern: '',
            match: $analysis['lhsSource'] . ' ' . $op . ' ' . implode('/', $analysis['cases']),
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [
                'op' => $op,
                'lhs' => $analysis['lhsSource'],
                'enum_short' => $analysis['shortName'],
                'enum_fqcn' => $resolvedFqcn,
                'cases' => implode(',', $analysis['cases']),
                'chain_length' => (string) count($analysis['cases']),
                'start' => (string) $root->getStartFilePos(),
                'end' => (string) $root->getEndFilePos(),
                'class_ref' => $this->renderClassRef($analysis['classNode']),
                'from_static' => $fromStatic ? '1' : '0',
            ],
        );
    }

    /**
     * Render the enum class reference exactly as written in source, preserving
     * a leading backslash for fully-qualified names so the rewrite needs no new
     * `use` import.
     */
    private function renderClassRef(Node\Name $name): string
    {
        return ($name->isFullyQualified() ? '\\' : '') . $name->toString();
    }

    private function isExcluded(string $resolvedFqcn, string $shortName): bool
    {
        if ($this->excludeEnums === []) {
            return false;
        }

        $fqcn = strtolower(ltrim($resolvedFqcn, '\\'));
        $short = strtolower($shortName);

        foreach ($this->excludeEnums as $entry) {
            if ($entry === $fqcn || $entry === $short) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $useStatements  alias => FQCN
     */
    private function resolveFqcn(Node\Name $name, array $useStatements, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($useStatements[$first])) {
            $parts[0] = $useStatements[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    /**
     * @param  array<int, Node>  $parentMap
     */
    private function isInsideWireFormatScope(Node $node, array $parentMap): bool
    {
        $current = $parentMap[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\ClassMethod
                && in_array($current->name->toString(), self::WIRE_FORMAT_METHODS, true)
            ) {
                return true;
            }

            if ($current instanceof Node\Stmt\Class_ && $current->extends !== null) {
                $parent = $current->extends->toString();

                foreach (self::WIRE_FORMAT_PARENT_SUFFIXES as $suffix) {
                    if ($parent === $suffix || str_ends_with($parent, '\\' . $suffix)) {
                        return true;
                    }
                }
            }

            $current = $parentMap[spl_object_id($current)] ?? null;
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<int, Node>
     */
    private function buildParentMap(array $ast): array
    {
        $parents = [];
        $stack = [];

        $walker = static function (mixed $node) use (&$walker, &$parents, &$stack): void {
            if (! $node instanceof Node) {
                return;
            }

            if (! empty($stack)) {
                $parents[spl_object_id($node)] = end($stack);
            }

            $stack[] = $node;

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};

                if (is_array($child)) {
                    foreach ($child as $c) {
                        $walker($c);
                    }
                } else {
                    $walker($child);
                }
            }

            array_pop($stack);
        };

        foreach ($ast as $node) {
            $walker($node);
        }

        return $parents;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
