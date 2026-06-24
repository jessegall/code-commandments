<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\OptionConsumptionResolver;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindNullableValueReturns;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * One rule for the null↔Option decision, so a method's absence is modelled exactly
 * when absence is real — and never contradicts itself. A return-type branch makes the
 * verdicts disjoint, so a method gets at most one:
 *
 *   A. ADOPT — a method that decides nothingness by returning `null` (an explicit
 *      `return null;` beside value returns) and whose callers each branch on it.
 *      Model the absence: return an Option (or a Null Object / empty value).
 *   B. NEVER-NONE — a method typed `: Option` whose every return is `Option::some(...)`.
 *      The value is never absent, so the Option only adds an unwrap; return the value.
 *   D. WRAP-THEN-UNWRAP — `Option::some($x)->unwrap()`: an Option built and unwrapped
 *      in one breath. Use the value directly.
 *
 * Unwrapping or querying an Option (`unwrap`/`isNone`/`unwrapOr`/branching on it) is
 * NORMAL and never the smell — only a type that misrepresents absence is.
 *
 *
 *
 *
 * @method-generated-start
 * @method static excludeMethods(array $value)
 * @method static frameworkMethods(array $value)
 * @method static minCallers(int $value)
 * @method static nullObjects(array $value)
 * @method static optionClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('3.8.0')]
class OptionDisciplineProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_EXCLUDED_METHODS = ['try*', '__*'];

    /**
     * Framework hook method names whose signature is the framework's, not the
     * author's — exempt when the class has a vendor ancestor.
     */
    private const DEFAULT_FRAMEWORK_METHODS = ['handle', 'morph', 'boot', 'booted', 'register', 'casts'];

    private const DEFAULT_MIN_CALLERS = 2;

    private const DEFAULT_OPTION_CLASS = 'JesseGall\\PhpTypes\\Option';

    private const DEFAULT_SOME_METHODS = ['some'];

    private const DEFAULT_NONE_METHODS = ['none'];

    /** Extractors that pull a value back out of an Option — a freshly-built Option followed by one of these is ceremony. */
    private const DEFAULT_UNWRAP_METHODS = ['unwrap', 'expect', 'unwrapOr', 'unwrapOrElse', 'toNullable'];

    private ?CodebaseIndex $index = null;

    /** @var array<\PhpParser\Node>|null  the file's AST for the current judge run */
    private ?array $sourceAst = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function description(): string
    {
        return 'Model absence exactly when it is real — adopt Option for value-or-nothing, never for always-value';
    }

    /**
     * Never flag the configured Option primitive itself. FQCN-matched, so a domain
     * class sharing the short name is still judged.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) ($this->config('option_class') ?: self::DEFAULT_OPTION_CLASS), '\\');

        return $class === '' ? [] : [$class];
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'The type lies about absence: a bare null that several callers must each '
                . 'de-null (one forgotten check is a TypeError), OR an Option that is '
                . 'never empty (every return is some()).'
            )
            ->leaveWhen(
                'The empty case is local and obvious (one or two callers), or the Option '
                . 'genuinely returns none() on some path and callers keep handling it. '
                . 'Then the type tells the truth — leave it.'
            )
            ->whenUnsure(
                'Match the type to whether absence can actually occur. Unwrapping or '
                . 'branching on an Option is normal and never the smell — only a type '
                . 'that misrepresents absence is. When in doubt, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Model absence exactly when absence is real. A method that returns a value OR
nothing should say so in its type; a method that always has a value should not
pretend it might not. This one rule owns the null↔Option decision so the two
directions can never tell you opposite things about the same code.

A — DECIDES NULL → adopt. Read the BODY: an explicit `return null;` beside a
value return pushes a hidden branch onto every caller (a `=== null`, `?->`, or
`??`), and forgetting one is a TypeError. Make the empty case a real type.

    Bad:
        private function sourceFor(string $id): PortRef|null
        {
            foreach ($this->edges as $edge) {
                if ($edge->id === $id) {
                    return $edge->ref;
                }
            }

            return null;   // every caller now writes its own null check
        }

    Good — the empty case is explicit and impossible to forget:
        private function sourceFor(string $id): Option
        {
            foreach ($this->edges as $edge) {
                if ($edge->id === $id) {
                    return Option::some($edge->ref);
                }
            }

            return Option::none();
        }

B — NEVER NONE → return the value. A method typed `: Option` whose every return
is `Option::some(...)` is never empty; the Option only adds an unwrap at each
call site. Return the value directly (or throw when it genuinely cannot exist).

    Bad:                                    Good:
        public function current(): Option       public function current(): Value
        {                                       {
            return Option::some($this->value);      return $this->value;
        }                                       }

D — WRAP THEN UNWRAP → drop it. `Option::some($x)->unwrap()` boxes a value only
to unbox it in the same breath. Use `$x`.

NOT a smell: unwrapping or querying an Option you RECEIVED (`->unwrap()`,
`->isNone()`, `->unwrapOr($d)`, branching on it). That is how you use an Option —
the type made the absence impossible to forget, and now you handle it. Adoption
(A) is gated on several callers actually branching on the null; a lone nearby
check is not worth an Option.

The Option type lives in jessegall/php-types (`JesseGall\PhpTypes\Option`); point
`option_class` at it (or your own). Configure:

    Backend\OptionDisciplineProphet::class => [
        'option_class' => JesseGall\PhpTypes\Option::class,
        'null_objects' => [
            App\Workflow\PortRef::class => App\Workflow\NullPortRef::class,
        ],
        'exclude_methods' => ['try*', '__*'],
        'min_callers' => 2,        // adopt (A) only when at least this many call
                                   // sites branch on the null
        'severity' => 'warning',   // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $this->sourceAst = $this->parse($content);

        // Case A — decides-null ⇒ adopt. The pipeline honours the exemptions and the
        // caller gate; severity decides sin vs warning.
        $pipe = (new FindNullableValueReturns)
            ->withExcludedMethods($this->resolveExcludedMethods());

        $adopt = PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->partitionMatches($this->translate(...))
            ->judge();

        $sins = $adopt->sins;
        $warnings = $adopt->warnings;

        // Cases B + D — overuse. Disjoint from A by return type / node, so no method
        // can earn both an adopt and an overuse verdict.
        if ($this->sourceAst !== null) {
            $finder = new NodeFinder;
            $optionShort = $this->optionShort();
            $this->flagAlwaysSomeMethods($finder, $this->sourceAst, $optionShort, $warnings);
            $this->flagConstructThenUnwrap($finder, $this->sourceAst, $optionShort, $warnings);
        }

        if ($sins === [] && $warnings === []) {
            return $this->righteous();
        }

        return new Judgment($sins, $warnings);
    }

    // ── Case A: decides-null ⇒ adopt ────────────────────────────────────

    private function translate(MatchResult $match): Sin|Warning|null
    {
        // A framework-locked signature isn't ours to retype to Option.
        if ($this->isFrameworkLocked($match)) {
            return null;
        }

        // A framework-contract method (Eloquent cast get/set), a request-boundary
        // parser, or a nullable-param passthrough returns null by the boundary's
        // idiom, not a domain decision.
        if ($this->isFrameworkContractMethod($match) || $this->isRequestBoundaryParser($match) || $this->isNullableParamPassthrough($match)) {
            return null;
        }

        $callers = $this->callerInfoFor($match);

        // Measure & suppress: when the index resolves how many call sites branch on
        // the null and that is below the threshold, the refactor isn't worth it.
        if ($callers['known'] && $callers['count'] < $this->minCallersThreshold()) {
            return null;
        }

        $message = $this->messageFor($match, $callers);
        $suggestion = $this->suggestionFor($match);
        $symbol = $match->groups['method'] ?? null;

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol);
    }

    /**
     * Resolve how many call sites BRANCH on this method's null. Zero resolved callers
     * is treated as UNKNOWN (a public method may have callers the index can't see),
     * not "unused"; only a positively-low count suppresses.
     *
     * @return array{known: bool, count: int}
     */
    private function callerInfoFor(MatchResult $match): array
    {
        if ($this->index === null) {
            return ['known' => false, 'count' => 0];
        }

        $fqcn = $match->groups['class_fqcn'] ?? '';
        $method = $match->groups['method_name'] ?? '';

        if ($fqcn === '' || $method === '') {
            return ['known' => false, 'count' => 0];
        }

        if ($this->index->callersOf($fqcn, $method) === []) {
            return ['known' => false, 'count' => 0];
        }

        // Count only callers that MANUALLY BRANCH on absence (`=== null` / `if (! $x)`)
        // — Option earns its place where callers juggle the null, not where they only
        // pass it on or read it nullsafe.
        $consumptions = (new OptionConsumptionResolver)->consumptions($fqcn, $method, $this->index);
        $branching = count(array_filter($consumptions, static fn (string $kind): bool => $kind === 'nullcheck'));

        return ['known' => true, 'count' => $branching];
    }

    /**
     * An Eloquent cast's contract method — `get()`/`set()` on a `CastsAttributes`
     * implementor. The nullable is the framework's, not a domain decision.
     */
    private function isFrameworkContractMethod(MatchResult $match): bool
    {
        if ($this->index === null) {
            return false;
        }

        if (! in_array(strtolower($match->groups['method_name'] ?? ''), ['get', 'set'], true)) {
            return false;
        }

        foreach ($this->index->interfacesOf(ltrim($match->groups['class_fqcn'] ?? '', '\\')) as $interface) {
            if (str_ends_with($interface, 'CastsAttributes')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A request-BOUNDARY parser: reads request input (`$this->input()`/`validated()`/…)
     * and returns null for absent/invalid input. Null is the HTTP boundary's idiom.
     */
    private function isRequestBoundaryParser(MatchResult $match): bool
    {
        $node = $this->methodNode($match);

        if ($node === null || $node->stmts === null) {
            return false;
        }

        $accessors = ['input', 'validated', 'string', 'array', 'integer', 'boolean', 'float', 'enum', 'date', 'collect', 'query', 'post', 'file', 'only', 'except', 'has', 'filled'];

        foreach ((new NodeFinder)->findInstanceOf($node->stmts, Node\Expr\MethodCall::class) as $call) {
            if ($call->var instanceof Node\Expr\Variable
                && $call->var->name === 'this'
                && $call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), $accessors, true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * A nullable-PASSTHROUGH: the method just relays the null of one of its own
     * nullable parameters — `return $value === null ? null : new static($value)`. The
     * null is the caller's mirrored back, not a nothingness this method decided.
     */
    private function isNullableParamPassthrough(MatchResult $match): bool
    {
        $node = $this->methodNode($match);

        if ($node === null || $node->stmts === null) {
            return false;
        }

        $nullableParams = [];

        foreach ($node->params as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name) && $this->typeAllowsNull($param->type)) {
                $nullableParams[$param->var->name] = true;
            }
        }

        if ($nullableParams === []) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($node->stmts, Node\Expr\Ternary::class) as $ternary) {
            if ($ternary->cond instanceof Node\Expr\BinaryOp\Identical || $ternary->cond instanceof Node\Expr\BinaryOp\NotIdentical) {
                foreach ([$ternary->cond->left, $ternary->cond->right] as $operand) {
                    if ($operand instanceof Node\Expr\Variable && is_string($operand->name) && isset($nullableParams[$operand->name])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function typeAllowsNull(?Node $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\Identifier) {
            return in_array($type->toLowerString(), ['null', 'mixed'], true);
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && $member->toLowerString() === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    private function methodNode(MatchResult $match): ?Node\Stmt\ClassMethod
    {
        if ($this->sourceAst === null) {
            return null;
        }

        $parts = explode('\\', ltrim($match->groups['class_fqcn'] ?? '', '\\'));
        $short = end($parts);
        $method = $match->groups['method_name'] ?? '';

        foreach ((new NodeFinder)->findInstanceOf($this->sourceAst, Node\Stmt\ClassLike::class) as $classLike) {
            if ($classLike->name === null || ($short !== '' && $classLike->name->toString() !== $short)) {
                continue;
            }

            foreach ($classLike->getMethods() as $candidate) {
                if ($candidate->name->toString() === $method) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    // ── Cases B + D: overuse ────────────────────────────────────────────

    /**
     * Case B — a method typed `: Option` whose EVERY return is `Option::some(...)`,
     * never `none()`: the value is never absent.
     *
     * @param  array<Node>  $ast
     * @param  list<Warning>  $warnings
     */
    private function flagAlwaysSomeMethods(NodeFinder $finder, array $ast, string $optionShort, array &$warnings): void
    {
        /** @var array<Node\Stmt\ClassMethod> $methods */
        $methods = $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->stmts === null || $this->returnTypeShort($method) !== $optionShort) {
                continue;
            }

            /** @var array<Node\Stmt\Return_> $returns */
            $returns = $finder->findInstanceOf($method->stmts, Node\Stmt\Return_::class);

            $anySome = false;

            foreach ($returns as $return) {
                // A none(), or anything that is not a some() construction (a variable,
                // a delegated/mapped Option, …) means absence may be real — stay silent.
                if ($this->optionConstructorKind($return->expr, $optionShort) !== 'some') {
                    continue 2;
                }

                $anySome = true;
            }

            if ($anySome) {
                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    sprintf(
                        '%s() is typed `: %s` but every return is `%s::some(...)` — it is never empty, so the Option only adds an unwrap at each call site. Return the value directly (or throw when it genuinely cannot be produced).',
                        $method->name->toString(),
                        $optionShort,
                        $optionShort,
                    ),
                    null,
                    'option-overuse-always-some:' . $method->name->toString(),
                );
            }
        }
    }

    /**
     * Case D — `Option::some($x)->unwrap()`: an Option constructed and unwrapped in
     * the same expression.
     *
     * @param  array<Node>  $ast
     * @param  list<Warning>  $warnings
     */
    private function flagConstructThenUnwrap(NodeFinder $finder, array $ast, string $optionShort, array &$warnings): void
    {
        $unwrap = $this->unwrapMethods();

        foreach ($finder->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $unwrap, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            if ($this->optionConstructorKind($call->var, $optionShort) === null) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    'An Option is constructed and immediately unwrapped with `->%s()` — pure ceremony. Use the value directly instead of wrapping then unwrapping it.',
                    $call->name->toString(),
                ),
                null,
                'option-overuse-unwrap',
            );
        }
    }

    /**
     * 'some' / 'none' / null — whether $expr is `Option::some(...)`, `Option::none()`,
     * or neither.
     */
    private function optionConstructorKind(?Node $expr, string $optionShort): ?string
    {
        if (! $expr instanceof Expr\StaticCall
            || ! $expr->class instanceof Node\Name
            || $expr->class->getLast() !== $optionShort
            || ! $expr->name instanceof Node\Identifier
        ) {
            return null;
        }

        $method = $expr->name->toString();

        if (in_array($method, $this->someMethods(), true)) {
            return 'some';
        }

        if (in_array($method, $this->noneMethods(), true)) {
            return 'none';
        }

        return null;
    }

    private function returnTypeShort(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        return $type instanceof Node\Name ? $type->getLast() : null;
    }

    private function optionShort(): string
    {
        $class = (string) ($this->config('option_class') ?: self::DEFAULT_OPTION_CLASS);
        $parts = explode('\\', ltrim($class, '\\'));

        return end($parts) ?: 'Option';
    }

    // ── Fluent config setters ───────────────────────────────────────────

    /** Minimum resolved call sites that branch on absence before A fires. */
    public function minCallers(int $count): static
    {
        return $this->setting('min_callers', $count);
    }

    /** The Option primitive to suggest / recognise. */
    public function optionClass(string $class): static
    {
        return $this->setting('option_class', $class);
    }

    /**
     * Per-return-type Null Object sentinels for case A.
     *
     * @param  array<class-string, class-string>  $map
     */
    public function nullObjects(array $map): static
    {
        return $this->setting('null_objects', $map);
    }

    /**
     * Method-name patterns to leave alone (default `try*`, `__*`).
     *
     * @param  list<string>  $patterns
     */
    public function excludeMethods(array $patterns): static
    {
        return $this->setting('exclude_methods', $patterns);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function minCallersThreshold(): int
    {
        $value = $this->config('min_callers', self::DEFAULT_MIN_CALLERS);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MIN_CALLERS;
    }

    /**
     * @param  array{known: bool, count: int}  $callers
     */
    private function messageFor(MatchResult $match, array $callers): string
    {
        $groups = $match->groups;
        $type = $groups['type_name'] !== '' ? $groups['type_name'] . ' | null' : 'a value | null';

        $callerClause = $callers['known']
            ? sprintf(
                ' %d call site%s carr%s this null check.',
                $callers['count'],
                $callers['count'] === 1 ? '' : 's',
                $callers['count'] === 1 ? 'ies' : 'y',
            )
            : '';

        return sprintf(
            '%s returns %s — the body decides nothingness (%s `return null`, %s value return%s).%s',
            $groups['method'],
            $type,
            $groups['null_count'],
            $groups['value_count'],
            $groups['value_count'] === '1' ? '' : 's',
            $callerClause,
        );
    }

    private function suggestionFor(MatchResult $match): string
    {
        $groups = $match->groups;
        $nullObject = $this->nullObjectFor($groups['type_fqcn'], $groups['type_name']);

        if ($nullObject !== null) {
            return sprintf(
                'Return `new %s` (configured null object for %s) instead of null, and let callers rely on its no-op behavior.',
                $this->shortClassName($nullObject),
                $groups['type_name'],
            );
        }

        $optionClass = (string) ($this->config('option_class') ?: self::DEFAULT_OPTION_CLASS);
        $short = $this->shortClassName($optionClass);

        return sprintf(
            'Wrap in %s: return %s::some($value) / %s::none(); callers use ->map()/->unwrapOr()/->expect() instead of null checks.',
            $optionClass,
            $short,
            $short,
        );
    }

    private function nullObjectFor(string $fqcn, string $typeName): ?string
    {
        $map = $this->config('null_objects', []);

        if (! is_array($map) || $map === []) {
            return null;
        }

        foreach ($map as $key => $nullObject) {
            if ($fqcn !== '' && strcasecmp($key, $fqcn) === 0) {
                return $nullObject;
            }

            if ($typeName !== '' && strcasecmp($this->shortClassName($key), $typeName) === 0) {
                return $nullObject;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolveExcludedMethods(): array
    {
        $patterns = $this->config('exclude_methods', self::DEFAULT_EXCLUDED_METHODS);

        return is_array($patterns) ? array_values($patterns) : self::DEFAULT_EXCLUDED_METHODS;
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    /**
     * Whether the flagged method's signature is dictated by a framework. True when its
     * name is a known framework hook AND the declaring class has a vendor ancestor.
     */
    private function isFrameworkLocked(MatchResult $match): bool
    {
        $name = $match->groups['method_name'] ?? '';

        if (! in_array($name, $this->frameworkMethods(), true)) {
            return false;
        }

        $classFqcn = ltrim((string) ($match->groups['class_fqcn'] ?? ''), '\\');

        if ($this->index === null || $classFqcn === '') {
            return true;
        }

        return $this->hasVendorAncestor($classFqcn);
    }

    /**
     * Whether $classFqcn's parent chain leads to a class the index does NOT know — a
     * vendor/framework base outside the scanned codebase.
     */
    private function hasVendorAncestor(string $classFqcn): bool
    {
        $summary = $this->index?->classByFqcn($classFqcn);
        $depth = 0;

        while ($summary !== null && $summary->parent !== null && $depth++ < 16) {
            $parent = ltrim($summary->parent, '\\');
            $parentSummary = $this->index?->classByFqcn($parent);

            if ($parentSummary === null) {
                return true;
            }

            $summary = $parentSummary;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function frameworkMethods(): array
    {
        $methods = $this->config('framework_methods', self::DEFAULT_FRAMEWORK_METHODS);

        return is_array($methods) && $methods !== [] ? array_values(array_map('strval', $methods)) : self::DEFAULT_FRAMEWORK_METHODS;
    }

    /**
     * @return list<string>
     */
    private function someMethods(): array
    {
        return $this->stringList('some_methods', self::DEFAULT_SOME_METHODS);
    }

    /**
     * @return list<string>
     */
    private function noneMethods(): array
    {
        return $this->stringList('none_methods', self::DEFAULT_NONE_METHODS);
    }

    /**
     * @return list<string>
     */
    private function unwrapMethods(): array
    {
        return $this->stringList('unwrap_methods', self::DEFAULT_UNWRAP_METHODS);
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(string $key, array $default): array
    {
        $configured = $this->config($key, $default);

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, 'is_string'))
            : $default;
    }
}
