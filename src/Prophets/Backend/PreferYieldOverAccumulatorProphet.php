<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The "collecting parameter" / "output parameter" smell (#126): a `void` method
 * whose real output is a side effect on a passed-in result-collector — every use
 * of the parameter is a discarded `$acc->mutator(...)` write, the parameter is
 * never read, and the method returns nothing. Threading ONE such accumulator
 * through a whole class (the "god accumulator") couples every method to a shared
 * mutable sink and makes each diagnostic untestable in isolation.
 *
 *     // before — the method's output is the side effect on $acc
 *     private function checkRequiredInputs(WorkflowGraph $g, WorkflowNode $n, GraphValidationAccumulator $acc): void
 *     {
 *         foreach ($n->inputs as $input) {
 *             if ($this->missing($g, $n, $input)) {
 *                 $acc->missingInput($n->id, $input->name);   // write-only param
 *             }
 *         }
 *     }
 *
 *     // after — pure function; results are first-class typed values the caller folds
 *     protected function checkNode(GraphValidationContext $ctx, WorkflowNode $n): iterable
 *     {
 *         foreach ($n->inputs as $input) {
 *             if ($this->missing($ctx, $n, $input)) {
 *                 yield new MissingRequiredInput($n->id, $input->name);
 *             }
 *         }
 *     }
 *
 * A result-collector is detected by SHAPE — a public surface of `void` APPEND
 * mutators (or a public array field appended via `$acc->prop[] = …`) plus a NO-ARG
 * terminal accessor returning `array`/iterable/Collection (`finalize`/`toArray`/
 * `all`/`result`…). The shape resolver deliberately distinguishes it from every
 * #126 LEAVE family, by AST/reflection rather than name lists:
 *   - a BUILDER whose product is returned by `build()`/`make()`/`create()` — even
 *     when the product type IS a `Collection` — is a builder you configure, not a
 *     gather-accessor (the "crux"): the product-returner overrides the terminal signal;
 *   - a fluent builder is left alone the moment ANY method returns `self`/`$this`
 *     (not only when fluent strictly outnumbers void), so a mostly-fluent builder
 *     with a couple of `void` escape-hatch helpers (`reset()`/`clear()`) stays quiet;
 *   - a registration/container surface — `register`/`bind`/`set`-named `void`
 *     mutators, or mutator bodies that do KEYED stores `$this->map[$key] = …` /
 *     `unset($this->map[$k])` rather than appends — is a hook you configure, not a
 *     collector, regardless of an incidental `getBindings()`/`all()` getter;
 *   - an event-sourcing AGGREGATE — `recordThat`, OR (by shape) a no-arg event
 *     puller (`pullEvents()`/`getUncommittedEvents()`) beside state-changing void
 *     mutators even with intent-named methods — is the sink by design;
 *   - a VISITOR is excluded whether it `extends *Visitor` OR `implements NodeVisitor`.
 * The `*Accumulator|*Collector|*Diagnostics|*Findings|*Errors|*Sink` suffix is only a
 * SECONDARY booster, used when the real shape is unresolvable AND the threaded calls
 * look like appends (never registrations). Only fires when >= `min_methods` methods
 * in the same class thread the same collector-typed write-only void param.
 *
 * Advisory, never a sin; not auto-fixable (yielding is a design call, and the FP
 * corpus — builder/registration hooks, aggregates, visitors, hot loops — is real).
 */
#[IntroducedIn('2.4.0')]
class PreferYieldOverAccumulatorProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Default minimum number of methods in a class that must thread the same collector before firing. */
    private const DEFAULT_MIN_METHODS = 3;

    /**
     * No-arg getter names a result-collector exposes to hand back the gathered
     * result. NOTE: a `build`/`make`/`create` returning a collection is treated as
     * a builder PRODUCT, never a terminal accessor (see isProductBuilderMethod()).
     */
    private const TERMINAL_ACCESSORS = ['finalize', 'toarray', 'all', 'result', 'results', 'getall', 'values', 'flush', 'collect', 'getitems', 'items'];

    /**
     * Method names that, returning a non-void product, mark a BUILDER's product
     * (`build()`/`make()`/`create()`) rather than a gather-accessor — even when the
     * product is itself a Collection. The discriminator the issue calls "the crux".
     */
    private const PRODUCT_BUILDER_NAMES = ['build', 'make', 'create', 'get', 'getrequest', 'getresult', 'tovalueobject', 'toobject'];

    /**
     * Verbs that mark a mutator as a key→value REGISTRATION hook (configuring an
     * object you were handed: a container/registry), not a result APPEND. A void
     * mutator whose name is one of these reads as registration, not collection.
     */
    private const REGISTRATION_VERBS = ['register', 'bind', 'singleton', 'scoped', 'instance', 'alias', 'extend', 'set', 'put', 'map', 'route', 'when', 'tag', 'define', 'declare', 'configure', 'with', 'on', 'listen', 'subscribe', 'provide'];

    /**
     * Verbs that mark a mutator as a genuine result APPEND (gathering a finding /
     * diagnostic / item into an internal list). These tip an ambiguous shape toward
     * "collector" rather than "registry".
     */
    private const APPEND_VERBS = ['add', 'append', 'push', 'collect', 'record', 'report', 'note', 'emit', 'write', 'error', 'warn', 'warning', 'fail', 'failure', 'invalid', 'violation', 'issue', 'problem', 'missing', 'mark', 'flag', 'gather'];

    /**
     * Method names whose presence marks a class as an event-sourcing aggregate (the
     * sink BY DESIGN). This is a SECONDARY confirmation — aggregate-ness is derived
     * primarily by SHAPE (recordable event mutators + an event-puller accessor).
     */
    private const AGGREGATE_MARKERS = ['recordthat', 'apply', 'releaseevents', 'getuncommittedevents', 'pulldomainevents'];

    /**
     * Substrings that, in a mutator/accessor name, signal event-sourcing semantics
     * (recording/pulling domain events) — used to derive aggregate-ness by shape
     * even when the magic `recordThat`/`releaseEvents` names are not used.
     */
    private const EVENT_NAME_FRAGMENTS = ['event', 'domainevent'];

    /** Parent-class short names that mark an aggregate root / visitor — excluded outright. */
    private const EXCLUDED_BASE_FRAGMENTS = ['AggregateRoot', 'Aggregate', 'EventSourced', 'Visitor', 'NodeVisitor', 'Container', 'Registry', 'Registrar', 'ServiceProvider'];

    /**
     * Secondary name booster: a parameter type whose short name ends with one of
     * these reads as a collector ONLY when a real shape could not be resolved AND
     * the threading mutators look like appends (see isResultCollector()). Suffixes
     * shared with config/registry containers (`Bag`, `Buffer`) are deliberately
     * weak and require corroborating append-shaped mutators.
     */
    private const COLLECTOR_NAME_SUFFIXES = ['Accumulator', 'Collector', 'Diagnostics', 'Findings', 'Errors', 'Sink'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Prefer returning / yielding typed results over threading a write-only accumulator parameter through a class';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('Several `void` methods in one class thread the SAME object-typed parameter that each method only WRITES to — every use is a discarded `$acc->mutator(...)` call (or a public-field append `$acc->prop[] = …`), never read/returned/passed-on — and that type is a result-collector (void APPEND mutators or a public array field, plus a no-arg terminal `finalize()`/`toArray()`/`all()`). The method\'s real output is the side effect on the accumulator.')
            ->leaveWhen('the parameter is a BUILDER you configure (its product is returned by `build()`/`make()`/`create()` — even a `Collection` product; or it is fluent, returning `self`/`$this`) — there is nothing to return, you are configuring an object you do not own; OR a REGISTRATION/container hook (a service-provider `register()`, `bind`/`set`-named mutators, or keyed stores `$map[$id] = …`); OR an event-sourcing AGGREGATE (`recordThat`, an `AggregateRoot`-like base, OR a no-arg `pullEvents()` puller beside state mutators — the aggregate IS the sink); OR a VISITOR (`extends *Visitor` or `implements NodeVisitor`); OR a measured HOT LOOP where allocating a result object per item is a real regression.')
            ->whenUnsure('ask whether the method could instead `return` / `yield` a typed value the caller folds. If yes, prefer that — each method becomes a pure, individually-testable function and each diagnostic a first-class typed value (no stringly-typed `->error("…")`, no shared mutable sink). If the parameter is genuinely a builder you were handed, leave it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A parameter you ONLY write to — every use is a discarded, void-returning mutator
call on it, never a read — is an OUTPUT parameter (Fowler's "collecting parameter").
The method's real result is the side effect on that object; it returns `void` to
hide that. Threading one accumulator through a whole class is the "god accumulator":
every method couples to a shared mutable sink, and no diagnostic is testable in
isolation.

Bad — write-only param, void method, threaded through the class:
    private function checkRequiredInputs(WorkflowGraph $g, WorkflowNode $n, GraphValidationAccumulator $acc): void
    {
        foreach ($n->inputs as $input) {
            if ($this->missing($g, $n, $input)) {
                $acc->missingInput($n->id, $input->name);   // write-only
                $acc->error("Required input '{$input->name}' is unwired");
            }
        }
    }

Good — pure function; first-class typed results the caller folds:
    protected function checkNode(GraphValidationContext $ctx, WorkflowNode $n): iterable
    {
        foreach ($n->inputs as $input) {
            if ($this->missing($ctx, $n, $input)) {
                yield new MissingRequiredInput($n->id, $input->name);
            }
        }
    }

WHAT FIRES — >= N (config `min_methods`, default 3) methods in ONE class that each
(1) return `void`, (2) take the SAME object-typed parameter every use of which is a
WRITE — a statement-level `$param->mutator(...)` call with a discarded return, OR a
public-field append `$param->prop[] = …` — and never a read (read into an
expression, returned, assigned-from, or passed on), and (3) whose type is a
RESULT-COLLECTOR shape: a gather surface (`void` APPEND mutators, or a public array
field) plus a NO-ARG terminal accessor returning array/iterable/Collection
(finalize/toArray/all/result…). The type is resolved by reflection / same-file AST /
the codebase index; a `*Accumulator|*Collector|*Diagnostics|*Findings|*Errors|*Sink`
suffix is a SECONDARY booster, used only when the real shape is unresolvable AND the
threaded calls look like appends.

WHAT DOES NOT — these are excluded by SHAPE, not a name blacklist:
  * a BUILDER whose product is returned by `build()`/`make()`/`create()` — even when
    the product type is itself a `Collection` (the "crux"): a product-returner is not
    a gather-accessor, so it overrides the terminal signal;
  * a FLUENT builder — excluded the moment ANY method returns `self`/`$this`, so a
    mostly-fluent builder with a couple of `void` `reset()`/`clear()` helpers is safe;
  * a REGISTRATION/container hook — `register`/`bind`/`set`-named void mutators, or
    mutator bodies that do KEYED stores `$this->map[$key] = …` / `unset(...)` instead
    of appends — you configure it, you do not own a gathered result;
  * an event-sourcing AGGREGATE — `recordThat`/`releaseEvents`, an `AggregateRoot`/
    `EventSourced` base, OR (by shape) a no-arg EVENT puller (`pullEvents()`) beside
    state-changing void mutators even with intent-named methods;
  * a VISITOR — whether it `extends *Visitor` OR `implements NodeVisitor`;
  * a one-off helper below `min_methods`; a method that READS the param.
Advisory — yielding is a design call, and these write-only patterns are legitimately
not-output-params.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Resolve names so a parameter's class type carries its FQCN for reflection
        // / index lookups, without replacing nodes (byte positions stay intact).
        (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($ast);

        $finder = new NodeFinder;
        $warnings = [];

        // Index same-file classes by short name so a collector type DECLARED in
        // this file can be inspected by AST when it isn't reflection-loadable.
        $this->sameFileIndex = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name !== null) {
                $this->sameFileIndex[$class->name->toString()] = $class;
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || $this->isExcludedClass($class)) {
                continue;
            }

            // Group the write-only-collector params by their resolved type FQCN (or
            // short name when unresolved) — the "threaded through everything" shape
            // is N methods sharing the SAME collector type. Each candidate also
            // records the mutator names it INVOKES on the param, so the collector
            // classification can tell an append surface from a registration hook.
            $byType = [];
            $mutatorsByType = [];

            foreach ($class->getMethods() as $method) {
                if (! $this->returnsVoid($method) || $method->stmts === null) {
                    continue;
                }

                foreach ($method->params as $param) {
                    $hit = $this->writeOnlyCollectorCandidate($method, $param);

                    if ($hit !== null) {
                        $byType[$hit['key']][] = [
                            'method' => $method->name->toString(),
                            'param' => $hit['param'],
                            'short' => $hit['short'],
                            'fqcn' => $hit['fqcn'],
                            'line' => $method->getStartLine(),
                        ];

                        $mutatorsByType[$hit['key']] = array_merge(
                            $mutatorsByType[$hit['key']] ?? [],
                            $hit['mutators'],
                        );
                    }
                }
            }

            foreach ($byType as $key => $hits) {
                if (count($hits) < $this->minMethods()) {
                    continue;
                }

                $first = $hits[0];

                // Now that the FULL threading surface is known, classify the type:
                // is it a result-collector (the smell) or a builder/registry/aggregate
                // you configure (LEAVE)? The called mutator names disambiguate.
                if (! $this->isResultCollector($first['fqcn'], $first['short'], $class, $mutatorsByType[$key])) {
                    continue;
                }

                // Point at the first threading method; bake the count into the message.
                $count = count($hits);

                $warnings[] = $this->warningAt(
                    $first['line'],
                    sprintf(
                        '$%s is an output parameter threaded through %d methods to accumulate results. Prefer returning / yield-ing typed values the caller folds, so each method is a pure, individually-testable function.',
                        $first['param'],
                        $count,
                    ),
                    $this->lineAt($content, $first['line']),
                    'collecting-parameter:' . $class->name->toString() . ':' . $first['short'],
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * If $param is a write-only param threaded through this void method, return its
     * identifying key (type FQCN or short name), the variable name, the type short
     * name, the resolved FQCN, and the set of mutator names invoked on it (a
     * property-append `$p->errors[] = …` contributes the empty-string sentinel ''
     * so the threading still counts but adds no registration/append name signal).
     * The collector-shape classification is deferred until the full surface is known.
     *
     * @return array{key: string, param: string, short: string, fqcn: ?string, mutators: list<string>}|null
     */
    private function writeOnlyCollectorCandidate(Node\Stmt\ClassMethod $method, Node\Param $param): ?array
    {
        if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
            return null;
        }

        // Must be an object type (a class), not a scalar / array / union with scalars.
        $typeName = $this->objectTypeName($param->type);

        if ($typeName === null) {
            return null;
        }

        $name = $param->var->name;
        $mutators = $this->writeOnlyMutators($method, $name);

        if ($mutators === null) {
            return null;
        }

        $short = $this->shortName($typeName);
        $fqcn = $this->resolveFqcn($param->type, $typeName);

        return [
            'key' => $fqcn ?? strtolower($short),
            'param' => $name,
            'short' => $short,
            'fqcn' => $fqcn,
            'mutators' => $mutators,
        ];
    }

    /**
     * If EVERY occurrence of $name in the method body is a write — either a
     * statement-level `$name->mutator(...)` call with a discarded return, or a
     * property-array append `$name->prop[] = …` (the same god-accumulator smell
     * expressed via a public field) — return the lowercased mutator names invoked
     * (property appends contribute ''). Any genuine READ (a value read into an
     * expression, an argument, a return, an assignment-from, a chained read used in
     * a condition) returns null.
     *
     * @return list<string>|null
     */
    private function writeOnlyMutators(Node\Stmt\ClassMethod $method, string $name): ?array
    {
        $finder = new NodeFinder;

        /** @var list<Expr\Variable> $uses */
        $uses = [];

        foreach ($finder->findInstanceOf($method->stmts, Expr\Variable::class) as $var) {
            if (is_string($var->name) && $var->name === $name) {
                $uses[] = $var;
            }
        }

        if ($uses === []) {
            return null; // never used at all — not threaded through this method
        }

        // Allowed write shapes whose receiver Variable counts as a write (not a read):
        //  (a) statement-level `$name->method(...)` — discarded mutator return;
        //  (b) `$name->prop[] = …` / `$name->prop[k] = …` — property-array append.
        $allowedReceivers = new \SplObjectStorage;
        $mutators = [];
        $sawWrite = false;

        foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\Expression::class) as $stmt) {
            $expr = $stmt->expr;

            // (a) discarded mutator call: $name->mutator(...);
            if ($expr instanceof Expr\MethodCall
                && $expr->var instanceof Expr\Variable
                && $expr->var->name === $name
                && $expr->name instanceof Node\Identifier
            ) {
                $allowedReceivers->offsetSet($expr->var, true);
                $mutators[] = strtolower($expr->name->toString());
                $sawWrite = true;

                continue;
            }

            // (b) property-array append: $name->prop[] = … (or [$key] = …)
            if ($expr instanceof Expr\Assign
                && $expr->var instanceof Expr\ArrayDimFetch
                && $expr->var->var instanceof Expr\PropertyFetch
                && $expr->var->var->var instanceof Expr\Variable
                && $expr->var->var->var->name === $name
            ) {
                $allowedReceivers->offsetSet($expr->var->var->var, true);
                $mutators[] = ''; // a public-field append carries no name signal
                $sawWrite = true;

                continue;
            }
        }

        if (! $sawWrite) {
            return null;
        }

        // Every use must be one of the allowed write receivers; anything else is a READ.
        foreach ($uses as $var) {
            if (! $allowedReceivers->offsetExists($var)) {
                return null;
            }
        }

        return $mutators;
    }

    /**
     * Whether the resolved type is a result-collector SHAPE: a public surface that
     * is predominantly `void` mutators plus a terminal accessor returning an
     * array/iterable/Collection. Resolved by reflection (autoloadable class),
     * same-file AST, or the codebase index. The name suffix is only a secondary
     * booster used when the shape is ambiguous/unresolvable.
     */
    /**
     * @param  list<string>  $threadedMutators  lowercased mutator names invoked on the param across all threading methods ('' = property-append)
     */
    private function isResultCollector(?string $fqcn, string $short, Node\Stmt\Class_ $enclosing, array $threadedMutators): bool
    {
        $nameSignal = $this->nameLooksLikeCollector($short);
        $callShape = $this->classifyThreadedCalls($threadedMutators);

        // 1) Reflection over a loadable class — the most reliable signal.
        if ($fqcn !== null && class_exists($fqcn)) {
            $shape = $this->reflectShape($fqcn);

            if ($shape !== null) {
                return $this->shapeIsCollector($shape, $nameSignal, $callShape);
            }
        }

        // 2) Same-file class node.
        $node = $this->classNodeInFile($short, $enclosing);

        // 3) Codebase index (cross-file).
        if ($node === null && $fqcn !== null && $this->index !== null) {
            $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

            if ($summary !== null) {
                $content = @file_get_contents($summary->filePath);

                if (is_string($content)) {
                    $node = $this->classNodeInContent($short, $content);
                }
            }
        }

        if ($node !== null) {
            if ($this->nodeIsExcludedBase($node) || $this->nodeIsAggregate($node)) {
                return false;
            }

            $shape = $this->astShape($node);

            if ($shape !== null) {
                return $this->shapeIsCollector($shape, $nameSignal, $callShape);
            }
        }

        // 4) Type genuinely unresolvable (fixture, vendor not loadable, outside
        // scroll) — no shape to inspect. Fall back to the name booster, but ONLY
        // when the threading calls look like result APPENDS, never registrations:
        // an unresolvable `*Bag`/`*Sink` you configure via `register(id, fn)` is a
        // registration hook, not a collector, and the name alone must not flip it.
        return $nameSignal && $callShape === 'append';
    }

    /**
     * Reduce the threaded mutator names to one of: 'register' (the calls look like
     * key→value registration into a container you configure), 'append' (the calls
     * look like appending a finding/item — the collector smell), or 'ambiguous'.
     *
     * @param  list<string>  $mutators
     */
    private function classifyThreadedCalls(array $mutators): string
    {
        $register = 0;
        $append = 0;

        foreach ($mutators as $name) {
            if ($name === '') {
                $append++; // a public-field array append is an append by construction

                continue;
            }

            if ($this->nameStartsWithAny($name, self::REGISTRATION_VERBS)) {
                $register++;
            } elseif ($this->nameStartsWithAny($name, self::APPEND_VERBS)) {
                $append++;
            }
        }

        if ($register > 0 && $register >= $append) {
            return 'register';
        }

        if ($append > 0) {
            return 'append';
        }

        return 'ambiguous';
    }

    private function nameStartsWithAny(string $name, array $verbs): bool
    {
        foreach ($verbs as $verb) {
            if (str_starts_with($name, $verb)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{void: int, fluent: int, terminal: bool, recognisedTerminal: bool, aggregate: bool, product: bool, registrationSurface: bool, publicArrayProp: bool}  $shape
     * @param  string  $callShape  'register' | 'append' | 'ambiguous'
     */
    private function shapeIsCollector(array $shape, bool $nameSignal, string $callShape): bool
    {
        if ($shape['aggregate']) {
            return false; // event-sourcing surface — the aggregate IS the sink by design
        }

        // A builder whose PRODUCT is returned by build()/make()/create() is a builder
        // you configure, not a result-collector — even when the product is a
        // Collection. (The discriminator the issue calls "the crux".) The presence of
        // a product-returner overrides the array-getter "terminal" signal.
        if ($shape['product']) {
            return false;
        }

        // ANY self-returning (fluent) setter is a builder signal — a predominantly
        // fluent builder that exposes a couple of void escape-hatch helpers
        // (reset()/clear()) must not tip into "collector".
        if ($shape['fluent'] >= 1 && $shape['fluent'] >= $shape['void'] - 1) {
            return false;
        }
        if ($shape['fluent'] > $shape['void']) {
            return false;
        }

        // A registration/container surface (exposes register/bind/singleton/set/…
        // void mutators) that you CONFIGURE is a hook, not a collector — regardless
        // of an incidental getBindings()/all() getter. The threaded calls confirm it.
        if ($shape['registrationSurface'] || $callShape === 'register') {
            return false;
        }

        // The collector with a real void-mutator surface: void mutators PLUS a
        // terminal accessor handing back the gathered result. The name suffix only
        // RELAXES the terminal requirement (a *Accumulator with void mutators reads as
        // a collector even without a recognised finalize() name), and even then only
        // when the threaded calls are appends, not registrations.
        if ($shape['void'] >= 1) {
            if ($shape['terminal']) {
                return true;
            }

            if ($nameSignal && $callShape !== 'register') {
                return true;
            }

            return false;
        }

        // No void-mutator surface — the threading appends straight into a public array
        // field (`$acc->errors[] = …`). This is the weakest evidence, so demand more:
        // a public array property AND a RECOGNISED terminal accessor name (toArray/
        // finalize/all/…) AND append-shaped threading. (A random class with a public
        // array field and an arbitrary collection getter must not qualify.)
        if ($shape['publicArrayProp'] && $shape['recognisedTerminal'] && $callShape === 'append') {
            return true;
        }

        return false;
    }

    /**
     * Reflect a loadable class's public surface into a collector shape.
     *
     * @return array{void: int, fluent: int, terminal: bool, recognisedTerminal: bool, aggregate: bool, product: bool, registrationSurface: bool, publicArrayProp: bool}|null
     */
    private function reflectShape(string $fqcn): ?array
    {
        try {
            $reflection = new ReflectionClass($fqcn);
        } catch (\ReflectionException) {
            return null;
        }

        $void = 0;
        $fluent = 0;
        $terminal = false;
        $recognisedTerminal = false;
        $aggregate = false;
        $product = false;
        $registration = 0;
        $self = strtolower($reflection->getShortName());
        $eventPuller = false;
        $publicArrayProp = false;

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propType = $property->getType();

            if (! $propType instanceof ReflectionNamedType || strtolower($propType->getName()) === 'array') {
                $publicArrayProp = true;
            }
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $lower = strtolower($method->getName());

            if (in_array($lower, self::AGGREGATE_MARKERS, true)) {
                $aggregate = true;
            }

            $return = $method->getReturnType();
            $returnName = $return instanceof ReflectionNamedType ? strtolower($return->getName()) : null;
            $noArgs = $method->getNumberOfParameters() === 0;

            if ($returnName === 'void') {
                $void++;

                if ($this->nameStartsWithAny($lower, self::REGISTRATION_VERBS)) {
                    $registration++;
                }
            } elseif ($returnName === 'self' || $returnName === 'static' || $returnName === $self) {
                $fluent++;
            } elseif ($this->isProductBuilderMethod($lower, $returnName)) {
                // build()/make()/create() returning a product (even a Collection) is a
                // builder product, NOT a gather-accessor.
                $product = true;
            } elseif ($noArgs && $this->isCollectionReturn($returnName)) {
                // A no-arg event puller (pullEvents()/getUncommittedEvents()) is an
                // AGGREGATE terminal, not a collector terminal — it hands back domain
                // events, not findings. Any other no-arg collection getter is the
                // collector's "hand back the gathered result" terminal. (Requiring no
                // args rejects a registry's keyed lookup like get(string $id): mixed.)
                if ($this->nameMentionsEvent($lower)) {
                    $eventPuller = true;
                } else {
                    $terminal = true;

                    if (in_array($lower, self::TERMINAL_ACCESSORS, true)) {
                        $recognisedTerminal = true;
                    }
                }
            }
        }

        // An event puller alongside state-changing void mutators is an aggregate even
        // without the magic recordThat/releaseEvents names (intent-named mutators).
        if ($eventPuller && $void >= 1) {
            $aggregate = true;
        }

        return [
            'recognisedTerminal' => $recognisedTerminal,
            'void' => $void,
            'fluent' => $fluent,
            'terminal' => $terminal,
            'aggregate' => $aggregate,
            'product' => $product,
            'registrationSurface' => $registration >= 1 && $registration * 2 >= $void,
            'publicArrayProp' => $publicArrayProp,
        ];
    }

    /**
     * Distil a class AST node's public surface into a collector shape.
     *
     * @return array{void: int, fluent: int, terminal: bool, recognisedTerminal: bool, aggregate: bool, product: bool, registrationSurface: bool, publicArrayProp: bool}|null
     */
    private function astShape(Node\Stmt\Class_ $class): ?array
    {
        $void = 0;
        $fluent = 0;
        $terminal = false;
        $recognisedTerminal = false;
        $aggregate = false;
        $product = false;
        $registration = 0;
        $eventPuller = false;
        $publicArrayProp = false;
        $keyedWrites = 0;
        $appendWrites = 0;
        $selfShort = $class->name?->toString();
        $selfLower = $selfShort !== null ? strtolower($selfShort) : null;

        foreach ($class->getProperties() as $property) {
            if (! $property->isPublic() || $property->isStatic()) {
                continue;
            }

            // A public array (or untyped) property is a gather sink for `$x->prop[] = …`.
            if ($property->type === null || $this->returnTypeShort($property->type) === 'array') {
                $publicArrayProp = true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic()) {
                continue;
            }

            $mName = $method->name->toString();

            if (str_starts_with($mName, '__')) {
                continue;
            }

            $lower = strtolower($mName);

            if (in_array($lower, self::AGGREGATE_MARKERS, true)) {
                $aggregate = true;
            }

            $returnName = $this->returnTypeShort($method->returnType);
            $returnLower = $returnName !== null ? strtolower($returnName) : null;
            $noArgs = $method->params === [];

            if ($returnLower === 'void') {
                $void++;

                if ($this->nameStartsWithAny($lower, self::REGISTRATION_VERBS)) {
                    $registration++;
                }

                // Inspect the mutator body: a KEYED store `$this->prop[$key] = …`
                // (or `unset(...)`) is registration into a map/registry; a bare
                // append `$this->prop[] = …` is gathering a result. This is the
                // genuine AST signal distinguishing `add($method, $uri)` (a route
                // registry) from `add($msg)` / `missingInput($id, $name)` (a collector).
                $this->tallyMutatorWrites($method, $keyedWrites, $appendWrites);
            } elseif ($returnLower === 'self' || $returnLower === 'static' || ($selfLower !== null && $returnLower === $selfLower)) {
                $fluent++;
            } elseif ($this->isProductBuilderMethod($lower, $returnLower)) {
                $product = true;
            } elseif ($noArgs && $returnName !== null && $this->isCollectionReturn($returnLower)) {
                // An event puller is an aggregate terminal, not a collector terminal.
                if ($this->nameMentionsEvent($lower)) {
                    $eventPuller = true;
                } else {
                    $terminal = true;

                    if (in_array($lower, self::TERMINAL_ACCESSORS, true)) {
                        $recognisedTerminal = true;
                    }
                }
            }
        }

        if ($eventPuller && $void >= 1) {
            $aggregate = true;
        }

        // The collector's own mutator bodies do KEYED stores (a map/registry you
        // configure) with no plain appends — a registration surface, not a collector.
        $keyedSurface = $keyedWrites >= 1 && $appendWrites === 0;

        return [
            'void' => $void,
            'fluent' => $fluent,
            'terminal' => $terminal,
            'recognisedTerminal' => $recognisedTerminal,
            'aggregate' => $aggregate,
            'product' => $product,
            'registrationSurface' => ($registration >= 1 && $registration * 2 >= $void) || $keyedSurface,
            'publicArrayProp' => $publicArrayProp,
        ];
    }

    /**
     * Tally the array writes a void mutator body performs on `$this->prop`:
     *  - `$this->prop[$key] = …` (non-empty index)  → a KEYED store (registration)
     *  - `unset($this->prop[$key])`                  → a KEYED store (registration)
     *  - `$this->prop[] = …`                         → an APPEND (gathering)
     */
    private function tallyMutatorWrites(Node\Stmt\ClassMethod $method, int &$keyed, int &$append): void
    {
        if ($method->stmts === null) {
            return;
        }

        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\ArrayDimFetch || ! $this->isThisPropertyFetch($assign->var->var)) {
                continue;
            }

            if ($assign->var->dim === null) {
                $append++;
            } else {
                $keyed++;
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\Unset_::class) as $unset) {
            foreach ($unset->vars as $var) {
                if ($var instanceof Expr\ArrayDimFetch && $this->isThisPropertyFetch($var->var)) {
                    $keyed++;
                }
            }
        }
    }

    private function isThisPropertyFetch(Node $node): bool
    {
        return $node instanceof Expr\PropertyFetch
            && $node->var instanceof Expr\Variable
            && $node->var->name === 'this';
    }

    /**
     * A `build()`/`make()`/`create()`-named method returning a non-void product
     * (object OR collection) is a builder's product-returner, not a gather-accessor.
     * This is the discriminator the issue calls "the crux": it keeps a non-fluent
     * builder whose product happens to be a Collection out of the collector bucket.
     */
    private function isProductBuilderMethod(string $lowerName, ?string $returnLower): bool
    {
        if ($returnLower === null || $returnLower === 'void' || $this->isScalarOrPseudo($returnLower)) {
            return false;
        }

        return in_array($lowerName, self::PRODUCT_BUILDER_NAMES, true);
    }

    private function nameMentionsEvent(string $lowerName): bool
    {
        foreach (self::EVENT_NAME_FRAGMENTS as $fragment) {
            if (str_contains($lowerName, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isCollectionReturn(?string $returnLower): bool
    {
        if ($returnLower === null) {
            return false;
        }

        return $returnLower === 'array'
            || $returnLower === 'iterable'
            || $returnLower === 'generator'
            || str_ends_with($returnLower, 'collection');
    }

    private function nameLooksLikeCollector(string $short): bool
    {
        foreach (self::COLLECTOR_NAME_SUFFIXES as $suffix) {
            if (str_ends_with($short, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedClass(Node\Stmt\Class_ $class): bool
    {
        return $this->nodeIsExcludedBase($class) || $this->nodeIsAggregate($class);
    }

    /**
     * Whether the class EXTENDS or IMPLEMENTS a base/interface marking it an
     * aggregate root, visitor, container, or registry — the LEAVE families. The
     * common visitor shape is `implements NodeVisitor` (not `extends`), so both the
     * parent name AND every implemented interface name are inspected.
     */
    private function nodeIsExcludedBase(Node\Stmt\Class_ $class): bool
    {
        $names = [];

        if ($class->extends instanceof Node\Name) {
            $names[] = $class->extends->getLast();
        }

        foreach ($class->implements as $interface) {
            if ($interface instanceof Node\Name) {
                $names[] = $interface->getLast();
            }
        }

        foreach ($names as $name) {
            foreach (self::EXCLUDED_BASE_FRAGMENTS as $fragment) {
                if (str_contains($name, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the class is an event-sourcing aggregate — by the magic markers
     * (recordThat/releaseEvents) OR by SHAPE: it exposes a no-arg, collection-
     * returning EVENT puller (a method whose name mentions "event", e.g.
     * `pullEvents()`/`getUncommittedEvents()`) alongside state-changing void
     * mutators. An aggregate's terminal hands back domain EVENTS, not findings — so
     * an intent-named aggregate (addLine/applyDiscount + pullEvents(): array) is the
     * sink-by-design the issue's LEAVE protects even without the magic names.
     */
    private function nodeIsAggregate(Node\Stmt\Class_ $class): bool
    {
        $eventPuller = false;
        $voidMutators = 0;

        foreach ($class->getMethods() as $method) {
            $lower = strtolower($method->name->toString());

            if (in_array($lower, self::AGGREGATE_MARKERS, true)) {
                return true;
            }

            if (! $method->isPublic() || $method->isStatic()) {
                continue;
            }

            $returnName = $this->returnTypeShort($method->returnType);
            $returnLower = $returnName !== null ? strtolower($returnName) : null;

            if ($returnLower === 'void') {
                $voidMutators++;
            } elseif ($method->params === [] && $returnName !== null && $this->isCollectionReturn($returnLower) && $this->nameMentionsEvent($lower)) {
                $eventPuller = true;
            }
        }

        return $eventPuller && $voidMutators >= 1;
    }

    private function returnsVoid(Node\Stmt\ClassMethod $method): bool
    {
        return $method->returnType instanceof Node\Identifier
            && strtolower($method->returnType->toString()) === 'void';
    }

    /**
     * The class type name of a parameter, or null when the type is missing, a
     * scalar/array/iterable, nullable, or a union (an output param is a single
     * concrete object).
     */
    private function objectTypeName(?Node $type): ?string
    {
        if (! $type instanceof Node\Name) {
            return null;
        }

        $last = $type->getLast();

        if ($this->isScalarOrPseudo($last)) {
            return null;
        }

        return $type->toString();
    }

    private function isScalarOrPseudo(string $name): bool
    {
        return in_array(strtolower($name), [
            'string', 'int', 'float', 'bool', 'array', 'object', 'mixed',
            'iterable', 'callable', 'void', 'never', 'null', 'true', 'false', 'parent', 'static', 'self',
        ], true);
    }

    private function resolveFqcn(?Node $type, string $typeName): ?string
    {
        if ($type instanceof Node\Name) {
            $resolved = $type->getAttribute('resolvedName');

            if ($resolved instanceof Node\Name) {
                return ltrim($resolved->toString(), '\\');
            }

            if ($type->isFullyQualified()) {
                return ltrim($type->toString(), '\\');
            }
        }

        return null;
    }

    private function shortName(string $typeName): string
    {
        $pos = strrpos($typeName, '\\');

        return $pos === false ? $typeName : substr($typeName, $pos + 1);
    }

    private function classNodeInFile(string $short, Node\Stmt\Class_ $enclosing): ?Node\Stmt\Class_
    {
        // Prefer the same-file index (built per-judge); fall back to the enclosing
        // node when the collector type IS the enclosing class (rare).
        return $this->sameFileIndex[$short] ?? ($enclosing->name?->toString() === $short ? $enclosing : null);
    }

    private function classNodeInContent(string $short, string $content): ?Node\Stmt\Class_
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $short) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Class nodes of the file under judgment, keyed by short name — set per-judge
     * so a same-file collector class can be inspected by AST.
     *
     * @var array<string, Node\Stmt\Class_>
     */
    private array $sameFileIndex = [];

    private function returnTypeShort(?Node $type): ?string
    {
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->getLast();
        }

        if ($type instanceof Node\NullableType) {
            return $this->returnTypeShort($type->type);
        }

        return null;
    }

    private function minMethods(): int
    {
        $value = $this->config('min_methods', self::DEFAULT_MIN_METHODS);

        return is_int($value) && $value >= 1 ? $value : self::DEFAULT_MIN_METHODS;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
