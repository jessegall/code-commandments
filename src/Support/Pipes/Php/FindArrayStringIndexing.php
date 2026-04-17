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
 * Find array subscript accesses that use a statically-known string key on a
 * variable that is not already known to be a dictionary.
 *
 * These are the "PHP array as poor-man's object" accesses — each one is a
 * candidate for being wrapped in a DTO / value object instead.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindArrayStringIndexing implements Pipe
{
    /**
     * Helper functions whose string argument is a path into dynamic data,
     * not a struct field. Accesses inside these calls are legitimate.
     */
    private const SKIP_FUNCTIONS = [
        'config', 'env', 'trans', '__',
        'data_get', 'data_set', 'data_forget',
        'request', 'session', 'cache', 'cookie',
    ];

    /**
     * Static helper classes whose methods operate on arrays as dictionaries.
     * Any access inside a call to one of these is legitimate.
     */
    private const SKIP_STATIC_CLASSES = ['Arr'];

    /**
     * Superglobals are genuine dictionaries. Subscripting them is a
     * different sin (raw request input) handled elsewhere.
     */
    private const SUPERGLOBALS = [
        '_GET', '_POST', '_REQUEST', '_COOKIE', '_SESSION',
        '_SERVER', '_ENV', '_FILES', 'GLOBALS',
    ];

    private const DICT_KEY_TYPE_PATTERN = '(?:string|int\|string|string\|int|array-key)';

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $parents = $this->buildParentMap($input->ast);
        [$scopedDictVars, $globalDictVars, $dictProps] = $this->collectDictInfo($input->ast, $parents);

        $nodeFinder = new NodeFinder;
        /** @var array<Expr\ArrayDimFetch> $accesses */
        $accesses = $nodeFinder->findInstanceOf($input->ast, Expr\ArrayDimFetch::class);

        $matches = [];
        $seen = [];

        foreach ($accesses as $access) {
            if ($access->dim === null) {
                continue;
            }

            $keyDisplay = $this->classifyKey($access->dim);

            if ($keyDisplay === null) {
                continue;
            }

            if ($this->isSuperglobalAccess($access->var)) {
                continue;
            }

            if ($this->isInsideSkippedCall($access, $parents, $input->useStatements)) {
                continue;
            }

            if ($this->isDictVariable($access, $parents, $scopedDictVars, $globalDictVars, $dictProps)) {
                continue;
            }

            $varSnippet = $this->extractSource($input->content, $access->var);
            $dedupeKey = $varSnippet . '[' . $keyDisplay . ']';

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $line = $access->getStartLine();
            $source = $this->describeSource($access, $parents);

            $matches[] = new MatchResult(
                name: $keyDisplay,
                pattern: '',
                match: $dedupeKey,
                line: $line,
                offset: null,
                content: $this->getSnippet($input->content, $line),
                groups: [
                    'var' => $varSnippet,
                    'key' => $keyDisplay,
                    'source_kind' => $source['kind'],
                    'source_hint' => $source['hint'],
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * Classify where the array being subscripted originates, so the
     * prophet can point at the place a DTO should be introduced.
     *
     * @param  array<int, Node>  $parents
     * @return array{kind: string, hint: string}
     */
    private function describeSource(Expr\ArrayDimFetch $access, array $parents): array
    {
        $var = $access->var;

        if ($var instanceof Expr\ArrayDimFetch) {
            return [
                'kind' => 'nested',
                'hint' => 'Wrap each level of this tree in its own DTO',
            ];
        }

        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            $prop = $var->name->toString();

            return [
                'kind' => 'property',
                'hint' => "Type the \$this->{$prop} property as a DTO (or array of DTOs)",
            ];
        }

        if ($var instanceof Expr\NullsafePropertyFetch
            && $var->name instanceof Node\Identifier
        ) {
            return [
                'kind' => 'property',
                'hint' => 'Type the property as a DTO so ?->prop returns a typed object',
            ];
        }

        if ($var instanceof Expr\MethodCall && $var->name instanceof Node\Identifier) {
            $method = $var->name->toString();

            return [
                'kind' => 'call',
                'hint' => "Change ->{$method}() to return a DTO instead of an array",
            ];
        }

        if ($var instanceof Expr\NullsafeMethodCall && $var->name instanceof Node\Identifier) {
            $method = $var->name->toString();

            return [
                'kind' => 'call',
                'hint' => "Change ?->{$method}() to return a DTO instead of an array",
            ];
        }

        if ($var instanceof Expr\StaticCall && $var->name instanceof Node\Identifier) {
            $method = $var->name->toString();

            return [
                'kind' => 'call',
                'hint' => "Change ::{$method}() to return a DTO instead of an array",
            ];
        }

        if ($var instanceof Expr\FuncCall && $var->name instanceof Node\Name) {
            $func = $var->name->toString();

            return [
                'kind' => 'call',
                'hint' => "Change {$func}() to return a DTO instead of an array",
            ];
        }

        if ($var instanceof Expr\Variable && is_string($var->name)) {
            $enclosing = $this->findEnclosingFunctionLike($access, $parents);

            if ($enclosing !== null && $this->variableIsParameter($enclosing, $var->name)) {
                $funcName = $this->functionLikeLabel($enclosing);

                return [
                    'kind' => 'param',
                    'hint' => "Replace the array \${$var->name} parameter of {$funcName} with a typed DTO",
                ];
            }

            if ($enclosing !== null) {
                $assignedFrom = $this->traceAssignmentSource($enclosing, $var->name);

                if ($assignedFrom !== null) {
                    return [
                        'kind' => 'call',
                        'hint' => "Change {$assignedFrom} to return a DTO instead of assigning an array to \${$var->name}",
                    ];
                }
            }

            return [
                'kind' => 'local',
                'hint' => "Hydrate \${$var->name} into a DTO at its assignment site",
            ];
        }

        return [
            'kind' => 'other',
            'hint' => 'Wrap this array in a DTO at the point it enters the codebase',
        ];
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function findEnclosingFunctionLike(Node $node, array $parents): ?Node
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($this->isFunctionLikeScope($current)) {
                return $current;
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return null;
    }

    /**
     * If the variable is assigned from a method/static/func call inside the
     * enclosing function, return a label for that call (e.g. "->find()").
     */
    private function traceAssignmentSource(Node $scope, string $varName): ?string
    {
        $nodeFinder = new NodeFinder;

        /** @var array<Expr\Assign> $assigns */
        $assigns = $nodeFinder->findInstanceOf($scope, Expr\Assign::class);

        foreach ($assigns as $assign) {
            if (! ($assign->var instanceof Expr\Variable)
                || $assign->var->name !== $varName
            ) {
                continue;
            }

            $expr = $assign->expr;

            if ($expr instanceof Expr\MethodCall && $expr->name instanceof Node\Identifier) {
                return '->' . $expr->name->toString() . '()';
            }

            if ($expr instanceof Expr\NullsafeMethodCall && $expr->name instanceof Node\Identifier) {
                return '?->' . $expr->name->toString() . '()';
            }

            if ($expr instanceof Expr\StaticCall && $expr->name instanceof Node\Identifier) {
                return '::' . $expr->name->toString() . '()';
            }

            if ($expr instanceof Expr\FuncCall && $expr->name instanceof Node\Name) {
                return $expr->name->toString() . '()';
            }
        }

        return null;
    }

    private function variableIsParameter(Node $functionLike, string $name): bool
    {
        if (! property_exists($functionLike, 'params')) {
            return false;
        }

        foreach ($functionLike->params as $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function functionLikeLabel(Node $functionLike): string
    {
        if ($functionLike instanceof Node\Stmt\ClassMethod) {
            return $functionLike->name->toString() . '()';
        }

        if ($functionLike instanceof Node\Stmt\Function_) {
            return $functionLike->name->toString() . '()';
        }

        if ($functionLike instanceof Node\Expr\Closure) {
            return 'the enclosing closure';
        }

        if ($functionLike instanceof Node\Expr\ArrowFunction) {
            return 'the enclosing arrow function';
        }

        return 'the enclosing function';
    }

    /**
     * Return a human-readable representation of a flagged key, or null if
     * the key isn't a statically-known string / constant.
     */
    private function classifyKey(Node $dim): ?string
    {
        if ($dim instanceof Scalar\String_) {
            return "'" . $dim->value . "'";
        }

        if ($dim instanceof Expr\ClassConstFetch) {
            return $this->renderClassConstFetch($dim);
        }

        if ($dim instanceof Expr\PropertyFetch
            && $dim->name instanceof Node\Identifier
            && $dim->name->toString() === 'value'
            && $dim->var instanceof Expr\ClassConstFetch
        ) {
            return $this->renderClassConstFetch($dim->var) . '->value';
        }

        return null;
    }

    private function renderClassConstFetch(Expr\ClassConstFetch $node): string
    {
        $class = $node->class instanceof Node\Name
            ? $node->class->toString()
            : '?';

        $const = $node->name instanceof Node\Identifier
            ? $node->name->toString()
            : '?';

        return $class . '::' . $const;
    }

    private function isSuperglobalAccess(Node $var): bool
    {
        return $var instanceof Expr\Variable
            && is_string($var->name)
            && in_array($var->name, self::SUPERGLOBALS, true);
    }

    /**
     * Walk upward through the parent chain. If any ancestor is a call to
     * config()/env()/data_get()/Arr::get()/etc., treat the subscript as
     * dynamic-dictionary access and skip it.
     *
     * @param  array<int, Node>  $parents  spl_object_id => parent node
     * @param  array<string, string>  $useStatements
     */
    private function isInsideSkippedCall(Node $node, array $parents, array $useStatements): bool
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Expr\FuncCall
                && $current->name instanceof Node\Name
                && in_array($current->name->toString(), self::SKIP_FUNCTIONS, true)
            ) {
                return true;
            }

            if ($current instanceof Expr\StaticCall
                && $current->class instanceof Node\Name
                && $this->staticCallMatchesSkipClass($current, $useStatements)
            ) {
                return true;
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return false;
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function staticCallMatchesSkipClass(Expr\StaticCall $call, array $useStatements): bool
    {
        /** @var Node\Name $class */
        $class = $call->class;
        $short = $class->getLast();
        $resolved = $useStatements[$short] ?? $class->toString();

        foreach (self::SKIP_STATIC_CLASSES as $skip) {
            if ($short === $skip) {
                return true;
            }

            if ($resolved === $skip) {
                return true;
            }

            if (str_ends_with($resolved, '\\' . $skip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skip when the subscripted variable is known to be a real dictionary
     * via PHPDoc (`@var array<string, T>` / `@param array<string, T>`).
     *
     * Variable lookups are scope-aware: a dict tag on a method's param does
     * not leak to other methods that happen to use the same variable name.
     * Property lookups are class-local (file-level here, which is the same
     * in practice for sane files).
     *
     * @param  array<int, Node>            $parents
     * @param  array<int, array<string, true>>  $scopedDictVars  scope_id => names
     * @param  array<string, true>          $globalDictVars
     * @param  array<string, true>          $dictProps
     */
    private function isDictVariable(
        Expr\ArrayDimFetch $access,
        array $parents,
        array $scopedDictVars,
        array $globalDictVars,
        array $dictProps,
    ): bool {
        $var = $this->rootSubscripted($access);

        if ($var instanceof Expr\Variable && is_string($var->name)) {
            $name = $var->name;

            $current = $parents[spl_object_id($access)] ?? null;

            while ($current !== null) {
                if ($this->isFunctionLikeScope($current)) {
                    if (isset($scopedDictVars[spl_object_id($current)][$name])) {
                        return true;
                    }
                }

                $current = $parents[spl_object_id($current)] ?? null;
            }

            return isset($globalDictVars[$name]);
        }

        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return isset($dictProps[$var->name->toString()]);
        }

        return false;
    }

    /**
     * Collect dictionary variable info from PHPDoc tags:
     * - `@param array<string, T> $name` on a function-like → scoped to it
     * - inline `/** @var array<string, T> $name *\/` → scoped to enclosing function
     * - `@var array<string, T>` on a class property → file-level property set
     *
     * @param  array<Node>  $ast
     * @param  array<int, Node>  $parents
     * @return array{0: array<int, array<string, true>>, 1: array<string, true>, 2: array<string, true>}
     */
    private function collectDictInfo(array $ast, array $parents): array
    {
        $scoped = [];
        $global = [];
        $props = [];

        $visitor = new class($scoped, $global, $props, $parents) extends NodeVisitorAbstract {
            /** @var array<int, array<string, true>> */
            public array $scoped;
            /** @var array<string, true> */
            public array $global;
            /** @var array<string, true> */
            public array $props;
            /** @var array<int, Node> */
            private array $parents;

            public function __construct(array &$scoped, array &$global, array &$props, array $parents)
            {
                $this->scoped = &$scoped;
                $this->global = &$global;
                $this->props = &$props;
                $this->parents = $parents;
            }

            public function enterNode(Node $node): ?int
            {
                if ($this->isFunctionLike($node)) {
                    $doc = $node->getDocComment();

                    if ($doc !== null) {
                        $names = $this->parseDictVarNames($doc->getText(), 'param');

                        if (! empty($names)) {
                            $id = spl_object_id($node);
                            $this->scoped[$id] = $this->scoped[$id] ?? [];

                            foreach ($names as $name) {
                                $this->scoped[$id][$name] = true;
                            }
                        }
                    }
                }

                if ($node instanceof Node\Stmt\Property) {
                    $doc = $node->getDocComment();

                    if ($doc !== null && $this->hasDictVarTag($doc->getText())) {
                        foreach ($node->props as $prop) {
                            $this->props[$prop->name->toString()] = true;
                        }
                    }
                }

                if ($node instanceof Node\Stmt\Expression) {
                    $doc = $node->getDocComment();

                    if ($doc !== null) {
                        $names = $this->parseDictVarNames($doc->getText(), 'var');

                        if (! empty($names)) {
                            $scopeId = $this->findEnclosingFunctionId($node);

                            foreach ($names as $name) {
                                if ($scopeId === null) {
                                    $this->global[$name] = true;
                                } else {
                                    $this->scoped[$scopeId] = $this->scoped[$scopeId] ?? [];
                                    $this->scoped[$scopeId][$name] = true;
                                }
                            }
                        }
                    }
                }

                return null;
            }

            private function isFunctionLike(Node $node): bool
            {
                return $node instanceof Node\Stmt\ClassMethod
                    || $node instanceof Node\Stmt\Function_
                    || $node instanceof Node\Expr\Closure
                    || $node instanceof Node\Expr\ArrowFunction;
            }

            /**
             * @return list<string>
             */
            private function parseDictVarNames(string $text, string $tag): array
            {
                $type = FindArrayStringIndexing::dictKeyTypePattern();
                $inner = '(?:[^<>]|<[^<>]*>)*';

                $pattern = '/@' . preg_quote($tag, '/')
                    . '\s+array<\s*' . $type . '\s*,' . $inner . '>\s+\$(\w+)/i';

                if (preg_match_all($pattern, $text, $matches)) {
                    return $matches[1];
                }

                return [];
            }

            private function hasDictVarTag(string $text): bool
            {
                $type = FindArrayStringIndexing::dictKeyTypePattern();
                $inner = '(?:[^<>]|<[^<>]*>)*';

                $pattern = '/@var\s+array<\s*' . $type . '\s*,' . $inner . '>/i';

                return (bool) preg_match($pattern, $text);
            }

            private function findEnclosingFunctionId(Node $node): ?int
            {
                $current = $this->parents[spl_object_id($node)] ?? null;

                while ($current !== null) {
                    if ($this->isFunctionLike($current)) {
                        return spl_object_id($current);
                    }

                    $current = $this->parents[spl_object_id($current)] ?? null;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return [$visitor->scoped, $visitor->global, $visitor->props];
    }

    public static function dictKeyTypePattern(): string
    {
        return self::DICT_KEY_TYPE_PATTERN;
    }

    /**
     * Drill down through nested ArrayDimFetch nodes to find the ultimate
     * variable/property/call being subscripted.
     */
    private function rootSubscripted(Expr\ArrayDimFetch $access): Node
    {
        $var = $access->var;

        while ($var instanceof Expr\ArrayDimFetch) {
            $var = $var->var;
        }

        return $var;
    }

    private function isFunctionLikeScope(Node $node): bool
    {
        return $node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Expr\ArrowFunction;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<int, Node>
     */
    private function buildParentMap(array $ast): array
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

    private function extractSource(string $content, Node $node): string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start === null || $end === null || $start < 0 || $end < $start) {
            return '?';
        }

        return substr($content, $start, $end - $start + 1);
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
