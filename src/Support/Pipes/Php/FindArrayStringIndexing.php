<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\JsonDocumentVariableResolver;
use JesseGall\CodeCommandments\Support\ExtractsLineSnippet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\OriginTracer;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use JesseGall\PhpTypes\T_String;

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
    use ExtractsLineSnippet;

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
     * Arr:: methods with an (array, key) signature. Calling one with a
     * statically-known single-segment key is the same sin as subscripting —
     * swapping the accessor syntax doesn't make the array a dictionary.
     */
    private const CIRCUMVENTION_ARR_METHODS = ['get', 'has', 'set', 'forget', 'pull', 'add', 'exists'];

    /**
     * Global helpers with an (array, key) signature, same rule as above.
     */
    private const CIRCUMVENTION_FUNCTIONS = ['data_get', 'data_set', 'data_forget'];

    /**
     * Superglobals are genuine dictionaries. Subscripting them is a
     * different sin (raw request input) handled elsewhere.
     */
    private const SUPERGLOBALS = [
        '_GET', '_POST', '_REQUEST', '_COOKIE', '_SESSION',
        '_SERVER', '_ENV', '_FILES', 'GLOBALS',
    ];

    private const DICT_KEY_TYPE_PATTERN = '(?:string|int\|string|string\|int|array-key|class-string(?:<[^<>]*>)?)';

    /**
     * Value types that do NOT make an array a dictionary. A heterogeneous
     * value type means the values differ per key — i.e. the keys are a
     * fixed, known set and the array is a record in disguise. Annotating
     * `array<string, mixed>` is not an opt-out; name the value type or
     * declare the exact shape (`array{...}`) instead.
     */
    private const NON_DICT_VALUE_TYPE_PATTERN = '(?:mixed|array)';

    private ?CodebaseIndex $codebaseIndex = null;

    private int $maxTraceDepth = 10;

    /**
     * Names of class constants declared with a STRING value in the current
     * file. Only these (used as `Class::CONST` keys) are record-field keys;
     * an int/float const like `T_Int::ZERO` is a numeric index, not a sin.
     *
     * @var list<string>
     */
    private array $stringConstNames = [];

    public function withCodebaseIndex(CodebaseIndex $index, int $maxDepth): self
    {
        $this->codebaseIndex = $index;
        $this->maxTraceDepth = max(1, $maxDepth);

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $this->stringConstNames = $this->collectStringConstNames($input->ast);
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

            if ($this->isDictNode($this->rootSubscripted($access), $access, $parents, $scopedDictVars, $globalDictVars, $dictProps)) {
                continue;
            }

            // #211: editing a deserialized JSON DOCUMENT (a variable from
            // `json_decode(...)`, or one re-encoded with `json_encode(...)`) is a
            // wire-format round-trip — composer.json, package manifests, API payloads
            // — not a domain bag to model as a DTO. Indexing it by key is the only way
            // to edit it.
            $rootVariable = $access->var;
            while ($rootVariable instanceof Expr\ArrayDimFetch) {
                $rootVariable = $rootVariable->var;
            }

            $scope = $rootVariable instanceof Expr\Variable ? $this->findEnclosingFunctionLike($access, $parents) : null;

            if ($rootVariable instanceof Expr\Variable
                && $scope instanceof Node\FunctionLike
                && (new JsonDocumentVariableResolver)->isJsonDocument($rootVariable, $scope)
            ) {
                continue;
            }

            $varSnippet = $this->extractSource($input->content, $access->var);
            $dedupeKey = $varSnippet . '[' . $keyDisplay . ']';

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $line = $access->getStartLine();
            $source = $this->describeSource($access->var, $access, $parents, $input->namespace);

            $matches[] = new MatchResult(
                name: $keyDisplay,
                pattern: T_String::empty(),
                match: $dedupeKey,
                line: $line,
                offset: null,
                content: $this->lineSnippet($input->content, $line),
                groups: [
                    'var' => $varSnippet,
                    'key' => $keyDisplay,
                    'source_kind' => $source['kind'],
                    'source_hint' => $source['hint'],
                ],
            );
        }

        $this->findWrapperCircumventions($input, $parents, $scopedDictVars, $globalDictVars, $dictProps, $seen, $matches);

        return $input->with(matches: $matches);
    }

    /**
     * Flag wrapper-helper calls used to dodge the subscript rule:
     * `Arr::get($graph, 'nodes')` is `$graph['nodes']` wearing a disguise.
     *
     * Legitimate wrapper uses stay exempt: dynamic keys, dotted deep paths
     * (`'nested.key'` — the one-off deep-config case), and targets annotated
     * as genuine dictionaries or exact shapes.
     *
     * @param  array<int, Node>  $parents
     * @param  array<int, array<string, true>>  $scopedDictVars
     * @param  array<string, true>  $globalDictVars
     * @param  array<string, true>  $dictProps
     * @param  array<string, true>  $seen
     * @param  array<MatchResult>  $matches
     */
    private function findWrapperCircumventions(
        mixed $input,
        array $parents,
        array $scopedDictVars,
        array $globalDictVars,
        array $dictProps,
        array &$seen,
        array &$matches,
    ): void {
        $nodeFinder = new NodeFinder;

        $calls = $nodeFinder->find(
            $input->ast,
            fn (Node $n): bool => $n instanceof Expr\StaticCall || $n instanceof Expr\FuncCall,
        );

        foreach ($calls as $call) {
            $via = $this->circumventionLabel($call, $input->useStatements);

            if ($via === null) {
                continue;
            }

            $args = $call->args;

            if (count($args) < 2
                || ! $args[0] instanceof Node\Arg
                || ! $args[1] instanceof Node\Arg
            ) {
                continue;
            }

            $target = $args[0]->value;
            $keyNode = $args[1]->value;
            $keyDisplay = $this->classifyKey($keyNode);

            if ($keyDisplay === null) {
                continue;
            }

            if ($keyNode instanceof Scalar\String_ && str_contains($keyNode->value, '.')) {
                continue;
            }

            if ($this->isSuperglobalAccess($target)) {
                continue;
            }

            $rootVar = $target instanceof Expr\ArrayDimFetch
                ? $this->rootSubscripted($target)
                : $target;

            if ($this->isDictNode($rootVar, $call, $parents, $scopedDictVars, $globalDictVars, $dictProps)) {
                continue;
            }

            $varSnippet = $this->extractSource($input->content, $target);
            $dedupeKey = $varSnippet . '[' . $keyDisplay . ']';

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $line = $call->getStartLine();
            $source = $this->describeSource($target, $call, $parents, $input->namespace);

            $matches[] = new MatchResult(
                name: $keyDisplay,
                pattern: T_String::empty(),
                match: $via . '(' . $varSnippet . ', ' . $keyDisplay . ')',
                line: $line,
                offset: null,
                content: $this->lineSnippet($input->content, $line),
                groups: [
                    'var' => $varSnippet,
                    'key' => $keyDisplay,
                    'via' => $via,
                    'source_kind' => $source['kind'],
                    'source_hint' => $source['hint'],
                ],
            );
        }
    }

    /**
     * Return a display label ("Arr::get", "data_get") when the call is a
     * wrapper helper with an (array, key) signature, null otherwise.
     *
     * @param  array<string, string>  $useStatements
     */
    private function circumventionLabel(Node $call, array $useStatements): ?string
    {
        if ($call instanceof Expr\FuncCall
            && $call->name instanceof Node\Name
            && in_array($call->name->toString(), self::CIRCUMVENTION_FUNCTIONS, true)
        ) {
            return $call->name->toString();
        }

        if ($call instanceof Expr\StaticCall
            && $call->class instanceof Node\Name
            && $call->name instanceof Node\Identifier
            && in_array($call->name->toString(), self::CIRCUMVENTION_ARR_METHODS, true)
            && $this->staticCallMatchesSkipClass($call, $useStatements)
        ) {
            return 'Arr::' . $call->name->toString();
        }

        return null;
    }

    /**
     * Classify where the accessed array originates, so the prophet can
     * point at the place a DTO should be introduced. `$context` is the
     * access/call node used for walking up to the enclosing scope.
     *
     * @param  array<int, Node>  $parents
     * @return array{kind: string, hint: string}
     */
    private function describeSource(Node $var, Node $context, array $parents, ?string $namespace): array
    {
        if ($var instanceof Expr\ArrayDimFetch) {
            return ['kind' => 'nested', 'hint' => 'Wrap each level of this tree in its own DTO'];
        }

        if ($var instanceof Expr\PropertyFetch && $var->var instanceof Expr\Variable && $var->var->name === 'this' && $var->name instanceof Node\Identifier) {
            return ['kind' => 'property', 'hint' => "Type the \$this->" . $var->name->toString() . " property as a DTO (or array of DTOs)"];
        }

        if ($var instanceof Expr\NullsafePropertyFetch && $var->name instanceof Node\Identifier) {
            return ['kind' => 'property', 'hint' => 'Type the property as a DTO so ?->prop returns a typed object'];
        }

        if ($var instanceof Expr\MethodCall && $var->name instanceof Node\Identifier) {
            return ['kind' => 'call', 'hint' => "Change ->" . $var->name->toString() . "() to return a DTO instead of an array"];
        }

        if ($var instanceof Expr\NullsafeMethodCall && $var->name instanceof Node\Identifier) {
            return ['kind' => 'call', 'hint' => "Change ??->" . $var->name->toString() . "() to return a DTO instead of an array"];
        }

        if ($var instanceof Expr\StaticCall && $var->name instanceof Node\Identifier) {
            return ['kind' => 'call', 'hint' => "Change ::" . $var->name->toString() . "() to return a DTO instead of an array"];
        }

        if ($var instanceof Expr\FuncCall && $var->name instanceof Node\Name) {
            return ['kind' => 'call', 'hint' => "Change " . $var->name->toString() . "() to return a DTO instead of an array"];
        }

        if ($var instanceof Expr\Variable && is_string($var->name)) {
            return $this->describeVariable($var, $context, $parents, $namespace);
        }

        return [
            'kind' => 'other',
            'hint' => 'Wrap this array in a DTO at the point it enters the codebase',
        ];
    }

    /**
     * Describe a variable's source (parameter, local, or assigned).
     *
     * @param  array<int, Node>  $parents
     * @return array{kind: string, hint: string}
     */
    private function describeVariable(Expr\Variable $var, Node $context, array $parents, ?string $namespace): array
    {
        $varName = $var->name;
        $enclosing = $this->findEnclosingFunctionLike($context, $parents);

        if ($enclosing !== null && $this->variableIsParameter($enclosing, $varName)) {
            $traced = $this->traceOrigin($enclosing, $context, $parents, $namespace, $varName);

            if ($traced !== null) {
                return $traced;
            }

            $funcName = $this->functionLikeLabel($enclosing);

            return [
                'kind' => 'param',
                'hint' => "Replace the array \${$varName} parameter of {$funcName} with a typed DTO",
            ];
        }

        if ($enclosing !== null) {
            $assignedFrom = $this->traceAssignmentSource($enclosing, $varName);

            if ($assignedFrom !== null) {
                return [
                    'kind' => 'call',
                    'hint' => "Change {$assignedFrom} to return a DTO instead of assigning an array to \${$varName}",
                ];
            }
        }

        return [
            'kind' => 'local',
            'hint' => "Hydrate \${$varName} into a DTO at its assignment site",
        ];
    }

    /**
     * Consult the codebase index (if injected) to walk upstream through
     * callers and name the DTO-introduction point.
     *
     * @param  array<int, Node>  $parents
     * @return array{kind: string, hint: string}|null
     */
    private function traceOrigin(
        Node $enclosing,
        Node $context,
        array $parents,
        ?string $namespace,
        string $varName,
    ): ?array {
        if ($this->codebaseIndex === null) {
            return null;
        }

        if (! $enclosing instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        $classNode = $this->findEnclosingClass($context, $parents);

        if ($classNode === null || $classNode->name === null) {
            return null;
        }

        $shortName = $classNode->name->toString();
        $classFqcn = $namespace !== null && T_String::isNotEmpty($namespace)
            ? $namespace . '\\' . $shortName
            : $shortName;

        $tracer = new OriginTracer($this->codebaseIndex, $this->maxTraceDepth);
        $trace = $tracer->trace($classFqcn, $enclosing->name->toString(), $varName);

        if ($trace === null) {
            return null;
        }

        return [
            'kind' => 'param_traced',
            'hint' => sprintf(
                'DTO boundary is %s::%s() (%d hop%s upstream, via %s) — introduce the DTO there instead of here',
                $trace->originClassFqcn,
                $trace->originMethod,
                $trace->hops,
                $trace->hops === 1 ? T_String::empty() : 's',
                $trace->reason,
            ),
        ];
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function findEnclosingClass(Node $node, array $parents): ?Node\Stmt\Class_
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\Class_) {
                return $current;
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return null;
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
            // `::class` is a class-string — a dictionary key, never a field.
            if ($dim->name instanceof Node\Identifier && strtolower($dim->name->toString()) === 'class') {
                return null;
            }

            // Only a class constant we can CONFIRM holds a string (declared in
            // this file) is a record-field key. `T_Int::ZERO` and other
            // numeric / unresolved constants are indices, not sins.
            if ($dim->name instanceof Node\Identifier
                && in_array($dim->name->toString(), $this->stringConstNames, true)
            ) {
                return $this->renderClassConstFetch($dim);
            }

            return null;
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

    /**
     * Names of class constants declared with a string value anywhere in the
     * file. Const names are effectively unique per file, so we key by name.
     *
     * @param  array<Node>  $ast
     * @return list<string>
     */
    private function collectStringConstNames(array $ast): array
    {
        $names = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\ClassConst::class) as $classConst) {
            foreach ($classConst->consts as $const) {
                if ($const->value instanceof Scalar\String_) {
                    $names[] = $const->name->toString();
                }
            }
        }

        return array_values(array_unique($names));
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
     * Skip when the accessed variable is known to be a real dictionary or
     * exact shape via PHPDoc (`@var` / `@param`). `$context` is the
     * access/call node used for walking up to the enclosing scope.
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
    private function isDictNode(
        Node $var,
        Node $context,
        array $parents,
        array $scopedDictVars,
        array $globalDictVars,
        array $dictProps,
    ): bool {
        if ($var instanceof Expr\Variable && is_string($var->name)) {
            $name = $var->name;

            $current = $parents[spl_object_id($context)] ?? null;

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
                        // Params describe INBOUND data, so a shape there is an
                        // honest contract — both dict types and shapes count.
                        $names = array_merge(
                            $this->parseDictTypeVarNames($doc->getText(), 'param'),
                            $this->parseShapeVarNames($doc->getText(), 'param'),
                        );

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
                        $dictNames = $this->parseDictTypeVarNames($doc->getText(), 'var');
                        $shapeNames = $this->parseShapeVarNames($doc->getText(), 'var');

                        // A `@var array{...}` shape does NOT bless a record you
                        // construct right here: `/** @var array{...} $x */ $x = [...]`
                        // is the dodge — annotating a literal you build instead of
                        // introducing a DTO. Genuine dictionaries (array<string, T>)
                        // building a homogeneous literal stay exempt.
                        $builtName = $this->assignedArrayLiteralVar($node);

                        if ($builtName !== null) {
                            $shapeNames = array_values(array_filter(
                                $shapeNames,
                                static fn (string $name): bool => $name !== $builtName,
                            ));
                        }

                        $names = array_merge($dictNames, $shapeNames);

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
             * Names tagged with a genuine dictionary type `array<string, T>`.
             *
             * @return list<string>
             */
            private function parseDictTypeVarNames(string $text, string $tag): array
            {
                $tag = preg_quote($tag, '/');
                $pattern = '/@' . $tag . '\s+' . $this->dictTypePattern() . '\s+\$(\w+)/i';

                if (preg_match_all($pattern, $text, $matches)) {
                    return $matches[1];
                }

                return [];
            }

            /**
             * Names tagged with a concrete-typed exact shape `array{...}`.
             *
             * @return list<string>
             */
            private function parseShapeVarNames(string $text, string $tag): array
            {
                $names = [];
                $tag = preg_quote($tag, '/');

                $shape = FindArrayStringIndexing::arrayShapePattern();
                $pattern = '/@' . $tag . '\s+(' . $shape . ')\s+\$(\w+)/i';

                if (preg_match_all($pattern, $text, $matches)) {
                    foreach ($matches[1] as $i => $shapeText) {
                        if (FindArrayStringIndexing::shapeDeclaresConcreteType($shapeText)) {
                            $names[] = $matches[2][$i];
                        }
                    }
                }

                return $names;
            }

            /**
             * The variable name when this statement assigns an array LITERAL to
             * it (`$x = [...]`) — i.e. a record built right here. Returns null
             * for anything else. Used to refuse blessing a shape annotation that
             * sits on a literal the author is constructing.
             */
            private function assignedArrayLiteralVar(Node\Stmt\Expression $node): ?string
            {
                $expr = $node->expr;

                if ($expr instanceof Expr\Assign
                    && $expr->var instanceof Expr\Variable
                    && is_string($expr->var->name)
                    && $expr->expr instanceof Expr\Array_
                ) {
                    return $expr->var->name;
                }

                return null;
            }

            private function hasDictVarTag(string $text): bool
            {
                if (preg_match('/@var\s+' . $this->dictTypePattern() . '/i', $text)) {
                    return true;
                }

                $shape = FindArrayStringIndexing::arrayShapePattern();

                if (preg_match('/@var\s+(' . $shape . ')/i', $text, $matches)) {
                    return FindArrayStringIndexing::shapeDeclaresConcreteType($matches[1]);
                }

                return false;
            }

            /**
             * A genuine dictionary: `array<string, T>` with a concrete T —
             * `mixed` and bare `array` don't count.
             */
            private function dictTypePattern(): string
            {
                $type = FindArrayStringIndexing::dictKeyTypePattern();
                $nonDict = FindArrayStringIndexing::nonDictValueTypePattern();
                $inner = '(?:[^<>]|<[^<>]*>)*';

                return 'array<\s*' . $type . '\s*,(?!\s*' . $nonDict . '\s*>)' . $inner . '>';
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

    public static function nonDictValueTypePattern(): string
    {
        return self::NON_DICT_VALUE_TYPE_PATTERN;
    }

    /**
     * PHPStan/Psalm array-shape annotation (`array{name: string, ...}`),
     * tolerating two levels of nested braces.
     */
    public static function arrayShapePattern(): string
    {
        return 'array\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\}';
    }

    /**
     * A shape only counts as typed when at least one entry declares a
     * concrete value type. `array{name?: mixed, type?: mixed}` is
     * `array<string, mixed>` in shape clothing — it declares nothing.
     */
    public static function shapeDeclaresConcreteType(string $shape): bool
    {
        return (bool) preg_match('/:\s*+(?!mixed\s*[,}])/i', $shape);
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

}
