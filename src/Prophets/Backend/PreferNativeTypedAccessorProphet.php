<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CallSite;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileAst;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionException;

/**
 * Flag an EXTERNAL coercion wrapped around a keyed read on a receiver that
 * already exposes a NATIVE typed accessor for that key — a Fluent-like bag
 * (Laravel `Request`/`Fluent`, or any object exposing keyed `string()`,
 * `integer()`, `boolean()`, `float()`, `array()` accessors).
 *
 * When `$request` is such a bag, `T_String::coerce($request->get('id'))`,
 * `(bool) $request->get('live', false)`, and `is_array($raw) ? $raw : []`
 * (over `$raw = $request->get('payload')`) all re-implement, by hand, a typed
 * accessor the receiver already provides — `$request->string('id')`,
 * `$request->boolean('live')`, `$request->array('payload')`. The accessor
 * guards the type AND defaults natively, so the wrapper (and any intermediate
 * variable + guard) is dead weight.
 *
 * Detection is reflection-driven, not name-driven: the receiver's resolved type
 * must STRUCTURALLY be a typed bag (it exposes the keyed-accessor family via
 * reflection) before any finding fires — a coincidental same-named method on a
 * plain DTO never trips it, and an unresolvable receiver stays silent.
 */
#[IntroducedIn('2.65.0')]
class PreferNativeTypedAccessorProphet extends PhpCommandment implements SinRepenter, NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    /** @var array<string, array{0: array<Node>, 1: array<string, string>, 2: ?string}|false> parsed caller files, by path */
    private array $callerCache = [];

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    /**
     * The php-types coercer classes whose `coerce()`/`coerceOrNull()` wrap a
     * keyed read — keyed by the type family they coerce to.
     */
    private const COERCERS = [
        'JesseGall\\PhpTypes\\T_String' => 'string',
        'JesseGall\\PhpTypes\\T_Int' => 'int',
        'JesseGall\\PhpTypes\\T_Float' => 'float',
        'JesseGall\\PhpTypes\\T_Bool' => 'bool',
    ];

    /**
     * The untyped bag getters a coercion is typically wrapped around.
     */
    private const UNTYPED_GETTERS = ['get', 'input', 'query'];

    /**
     * Conventional accessor method names per type family. We never classify a
     * type FROM these names — we reflect the resolved receiver type and require
     * the method to actually EXIST (and a family of them to exist) before
     * trusting the convention. The name is only how we address the verified
     * method in the suggestion.
     *
     * @var array<string, list<string>>
     */
    private const ACCESSOR_NAMES = [
        'string' => ['string', 'str'],
        'int' => ['integer', 'int'],
        'float' => ['float', 'double'],
        'bool' => ['boolean', 'bool'],
        'array' => ['array', 'collect'],
    ];

    /**
     * Type-guard predicates that, with a kept-value branch, stand in for a
     * typed accessor — keyed by the family the accessor returns.
     */
    private const GUARD_FAMILIES = [
        'is_array' => 'array',
        'is_string' => 'string',
        'is_int' => 'int',
        'is_integer' => 'int',
        'is_bool' => 'bool',
    ];

    /** The literal source that equals a family's "zero" default (droppable). */
    private const FAMILY_ZEROS = [
        'string' => ["''", '""', 'null'],
        'int' => ['0', 'null'],
        'float' => ['0.0', '0', 'null'],
        'bool' => ['false', 'null'],
        'array' => ['[]', 'array()', 'null'],
    ];

    public function description(): string
    {
        return 'Use the receiver\'s native typed accessor instead of coercing its untyped get()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A value read from a Fluent-like bag (Laravel `Request`/`Fluent`, or any object exposing keyed typed accessors) is externally coerced — `T_String::coerce($request->get(\'id\'))`, `(bool) $request->get(\'live\', false)`, or `is_array($raw) ? $raw : []` over `$raw = $request->get(\'payload\')`. The receiver already exposes `string()/integer()/boolean()/float()/array()` for that key, so the wrapper re-implements it. Call the accessor directly: `$request->string(\'id\')`, `$request->boolean(\'live\')`, `$request->array(\'payload\')`.')
            ->leaveWhen('the receiver is NOT a typed bag (no native accessor for that family — a plain array, a DTO, an unresolvable/chained receiver: this rule stays silent there), or the coercion target differs from any accessor the bag offers, or you genuinely need the coercer\'s stricter semantics (e.g. `T_Int::coerce` rejecting non-numeric where the bag\'s `integer()` casts).')
            ->whenUnsure('replace the wrapper with the matching accessor and drop any intermediate variable + guard — `$request->array(\'payload\')` returns a guaranteed array, so `$raw = $request->get(\'payload\'); $payload = is_array($raw) ? $raw : [];` collapses to one call. If the receiver turns out not to expose the accessor, the rule will not have fired in the first place.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A Fluent-like bag — Laravel's `Request`/`Fluent`, or any object exposing keyed
typed accessors (`string()`, `integer()`, `boolean()`, `float()`, `array()`) —
already does the type work. Reaching for its UNTYPED `get()`/`input()` and then
coercing the `mixed` result by hand re-implements the accessor the bag ships,
badly: the cast and the default drift from the canonical accessor, and an
intermediate variable + guard appears where one call would do.

Bad — three ways agents hand-roll an accessor that already exists:
    T_String::coerce($request->get('id'))
    (bool) $request->get('live', false)

    $rawPayload = $request->get('payload');
    /** @var array<string, mixed> $payload */
    $payload = is_array($rawPayload) ? $rawPayload : [];

Good — the receiver's native accessor (guards the type AND defaults natively):
    $request->string('id')
    $request->boolean('live')
    $request->array('payload')      // the intermediate + guard vanish entirely

WHAT FIRES — an external coercion wrapping a keyed read `$recv->get($key)` /
`$recv->input($key)` / `$recv[$key]`, in one of three forms:
  1. a php-types coercer — `T_String|T_Int|T_Float|T_Bool::coerce|coerceOrNull(…)`,
  2. a native cast — `(string|int|float|bool) …`,
  3. a type-guard ternary — `is_array|is_string|is_int|is_bool($v) ? $v : default`
     (over the read directly, or an intermediate variable assigned from it),
WHEN the receiver's resolved type STRUCTURALLY exposes the matching keyed
accessor (verified by reflection — a family of typed accessors must exist, so a
coincidental same-named method on a DTO never trips it).

The value need not be coerced where it is read. AST traces it through local
variables (any distance within the method — read at the top, guard 40 lines
down), and — with the codebase index — ACROSS method boundaries: a parameter
coerced/guarded here, fed `$bag->get($key)` from its call sites, fires too. The
interprocedural case only fires when EVERY resolved caller passes such a read
(the OriginTracer convergence rule) — one caller passing anything else means
the parameter is genuinely polymorphic and nothing fires.

WHAT DOES NOT — an unresolvable or chained receiver (LEAVE: stays silent), a
plain array / a DTO with no accessor family, a coercion whose target the bag
does not offer, or a `get()` with no surrounding coercion (nothing to simplify).

[AUTO-FIXABLE] for the coercer and cast forms — `repent` rewrites
`T_String::coerce($request->get('id'))` → `$request->string('id')` and
`(bool) $request->get('live', false)` → `$request->boolean('live')` (a default
equal to the accessor's natural zero is dropped; a meaningful default is carried
through). The guard-ternary form is advisory only — collapsing it also removes
the now-dead intermediate variable, which is left to the author.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $namespace = $this->getNamespace($ast);
        $uses = FileImports::of($ast);
        $warnings = [];

        foreach ($this->findings($ast, $content, $uses, $namespace) as $finding) {
            $warnings[] = $this->warningAt(
                $finding['line'],
                $finding['message'],
                $this->lineSnippet($content, $finding['line']),
                'native-accessor:' . $finding['family'] . ':' . $finding['key'],
                $finding['autoFixable'],
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Every coercion-of-a-typed-bag site in the file.
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     * @return list<array{line: int, family: string, key: string, accessor: string, message: string, autoFixable: bool}>
     */
    private function findings(array $ast, string $content, array $uses, ?string $namespace): array
    {
        $finder = new NodeFinder;
        $findings = [];

        // 1 + 2: coercer static calls and native casts wrapping a keyed read —
        // directly, OR over a local variable that traces back (via AST) to the
        // read, so a value split across lines and coerced where it is USED (e.g.
        // passed down into another method) is caught just the same.
        foreach ($finder->find($ast, fn (Node $n): bool => $this->coercionWrapper($n, $uses, $namespace) !== null) as $node) {
            $wrap = $this->coercionWrapper($node, $uses, $namespace);
            $read = $this->keyedRead($wrap['inner']);
            $autoFixable = $wrap['autoFixable'];

            if ($read === null) {
                // The wrapper coerces a variable — trace it to its origin read.
                // The fix then touches a separate line (the read), so it is not
                // a safe in-place rewrite: advisory only.
                $read = $this->readFromVariable($wrap['inner'], $node, $ast);
                $autoFixable = false;
            }

            if ($read === null) {
                continue;
            }

            $finding = $this->resolve($read, $wrap['family'], $node, $ast, $uses, $namespace, $content, $autoFixable);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        // 3: type-guard ternaries (advisory only — collapsing removes dead vars).
        foreach ($finder->findInstanceOf($ast, Expr\Ternary::class) as $ternary) {
            $guard = $this->guardCoercion($ternary, $ast);

            if ($guard === null) {
                continue;
            }

            $finding = $this->resolve($guard['read'], $guard['family'], $ternary, $ast, $uses, $namespace, $content, false);

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        // 4: interprocedural — the coerced/guarded value is a PARAMETER fed a
        // typed-bag read from every call site (the read lives in another method,
        // even another file). Needs the codebase index.
        foreach ($this->interproceduralFindings($ast, $content, $uses, $namespace) as $finding) {
            $findings[] = $finding;
        }

        return $findings;
    }

    /**
     * Findings where the coercion/guard targets an untyped PARAMETER that every
     * resolved caller feeds a typed-bag read — the read is in the caller, the
     * redundant coercion in the callee. Reuses the OriginTracer convergence
     * rule: ALL callers must agree, or the parameter is genuinely polymorphic
     * and nothing fires.
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     * @return list<array{line: int, family: string, key: string, accessor: string, message: string, autoFixable: bool}>
     */
    private function interproceduralFindings(array $ast, string $content, array $uses, ?string $namespace): array
    {
        if ($this->index === null) {
            return [];
        }

        $classFqcn = $this->getFullyQualifiedClassName($ast);

        if ($classFqcn === null) {
            return [];
        }

        $finder = new NodeFinder;
        $out = [];

        // Coercer/cast wrapping a bare parameter variable.
        foreach ($finder->find($ast, fn (Node $n): bool => $this->coercionWrapper($n, $uses, $namespace) !== null) as $node) {
            $wrap = $this->coercionWrapper($node, $uses, $namespace);

            if ($wrap['inner'] instanceof Expr\Variable && $this->keyedRead($wrap['inner']) === null) {
                $finding = $this->interprocedural($wrap['inner'], $wrap['family'], $node, $ast, $classFqcn);

                if ($finding !== null) {
                    $out[] = $finding;
                }
            }
        }

        // Type-guard ternary over a bare parameter variable.
        foreach ($finder->findInstanceOf($ast, Expr\Ternary::class) as $ternary) {
            $shape = $this->guardShape($ternary);

            if ($shape !== null && $shape['value'] instanceof Expr\Variable && $this->keyedRead($shape['value']) === null) {
                $finding = $this->interprocedural($shape['value'], $shape['family'], $ternary, $ast, $classFqcn);

                if ($finding !== null) {
                    $out[] = $finding;
                }
            }
        }

        return $out;
    }

    /**
     * If $node is a php-types coercer call or a native cast wrapping an inner
     * expression, the coerced family + that inner expression.
     *
     * @param  array<string, string>  $uses
     * @return array{family: string, inner: Expr, autoFixable: bool}|null
     */
    private function coercionWrapper(Node $node, array $uses, ?string $namespace): ?array
    {
        if ($node instanceof Expr\StaticCall
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), ['coerce', 'coerceOrNull'], true)
        ) {
            $fqcn = ltrim(NameResolver::resolve($node->class->toString(), $uses, $namespace), '\\');
            $family = self::COERCERS[$fqcn] ?? null;
            $arg = $node->args[0] ?? null;

            if ($family !== null && $arg instanceof Node\Arg) {
                return ['family' => $family, 'inner' => $arg->value, 'autoFixable' => true];
            }
        }

        $castFamily = match (true) {
            $node instanceof Expr\Cast\String_ => 'string',
            $node instanceof Expr\Cast\Int_ => 'int',
            $node instanceof Expr\Cast\Double => 'float',
            $node instanceof Expr\Cast\Bool_ => 'bool',
            default => null,
        };

        if ($castFamily !== null) {
            /** @var Expr\Cast $node */
            return ['family' => $castFamily, 'inner' => $node->expr, 'autoFixable' => true];
        }

        return null;
    }

    /**
     * If $ternary is `is_x($v) ? $v : default` keeping a keyed-read value, the
     * accessor family + the underlying read (resolving an intermediate var).
     *
     * @param  array<Node>  $ast
     * @return array{family: string, read: array{recv: Expr, key: string}}|null
     */
    private function guardCoercion(Expr\Ternary $ternary, array $ast): ?array
    {
        $shape = $this->guardShape($ternary);

        if ($shape === null) {
            return null;
        }

        // The guarded value is either the keyed read itself, or a variable
        // assigned from one earlier in the enclosing function.
        $read = $this->keyedRead($shape['value']) ?? $this->readFromVariable($shape['value'], $ternary, $ast);

        return $read === null ? null : ['family' => $shape['family'], 'read' => $read];
    }

    /**
     * If $ternary is `is_x($v) ? $v : default` (an identity coercion keeping the
     * guarded value), the accessor family + the guarded value expression — with
     * no attempt yet to resolve $v to a read (so both the local and the
     * interprocedural paths can share the structural match).
     *
     * @return array{family: string, value: Expr}|null
     */
    private function guardShape(Expr\Ternary $ternary): ?array
    {
        if ($ternary->if === null || ! $ternary->cond instanceof Expr\FuncCall || ! $ternary->cond->name instanceof Node\Name) {
            return null;
        }

        $family = self::GUARD_FAMILIES[strtolower($ternary->cond->name->toString())] ?? null;
        $arg = $ternary->cond->args[0] ?? null;

        if ($family === null || ! $arg instanceof Node\Arg) {
            return null;
        }

        // The kept branch must be the same guarded value (identity coercion).
        return $this->sameValue($ternary->if, $arg->value)
            ? ['family' => $family, 'value' => $arg->value]
            : null;
    }

    /**
     * Build an interprocedural finding when $param (a coerced/guarded variable)
     * is an untyped parameter that EVERY resolved caller feeds a typed-bag read
     * — else null (a typed param, no callers, or any caller passing something
     * else: the parameter is polymorphic and nothing fires).
     *
     * @param  array<Node>  $ast
     * @return array{line: int, family: string, key: string, accessor: string, message: string, autoFixable: bool}|null
     */
    private function interprocedural(Expr\Variable $param, string $family, Node $site, array $ast, string $classFqcn): ?array
    {
        if (! is_string($param->name) || $this->index === null) {
            return null;
        }

        $slot = $this->parameterSlot($param->name, $site, $ast);

        // Only an untyped/`mixed` param can receive the bag's untyped `get()`.
        if ($slot === null || $slot['typed']) {
            return null;
        }

        $callers = $this->index->callersOf($classFqcn, $slot['method']);

        if ($callers === []) {
            return null;
        }

        $accessor = null;
        $key = null;
        $sites = [];

        foreach ($callers as $caller) {
            $resolved = $this->resolveCallerArg($caller, $slot['index'], $param->name, $family);

            if ($resolved === null) {
                // A caller passing a non-bag value (or unverifiable) means the
                // parameter is not uniformly a typed-bag read — bail entirely.
                return null;
            }

            $accessor = $resolved['accessor'];
            $key = $resolved['key'];
            $sites[] = basename($caller->callerFile) . ':' . $caller->line;
        }

        if ($accessor === null || $key === null) {
            return null;
        }

        return [
            'line' => $site->getStartLine(),
            'family' => $family,
            'key' => $key,
            'accessor' => $accessor,
            'message' => $this->interprocMessage($param->name, $accessor, $key, $sites),
            'autoFixable' => false,
        ];
    }

    /**
     * The position + typedness of parameter $name in the ClassMethod enclosing
     * $site, or null when $site is not inside a class method with that param.
     *
     * @param  array<Node>  $ast
     * @return array{method: string, index: int, typed: bool}|null
     */
    private function parameterSlot(string $name, Node $site, array $ast): ?array
    {
        $fn = ReceiverTypeResolver::enclosingFunction($site, $ast);

        if (! $fn instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        foreach ($fn->params as $index => $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $name) {
                $typed = $param->type !== null
                    && ! ($param->type instanceof Node\Identifier && strtolower($param->type->toString()) === 'mixed');

                return ['method' => $fn->name->toString(), 'index' => $index, 'typed' => $typed];
            }
        }

        return null;
    }

    /**
     * Resolve the argument $caller passes in slot $index: re-parse the caller
     * file, locate the call, and confirm the argument is (or traces to) a typed
     * bag read whose $family accessor exists — returning that accessor + key, or
     * null when the caller cannot be verified as passing such a read.
     *
     * @return array{accessor: string, key: string}|null
     */
    private function resolveCallerArg(CallSite $caller, int $index, string $paramName, string $family): ?array
    {
        if ($caller->startFilePos < 0) {
            return null;
        }

        $parsed = $this->parseCaller($caller->callerFile);

        if ($parsed === false) {
            return null;
        }

        [$ast, $uses, $namespace] = $parsed;

        $call = $this->callAt($ast, $caller->startFilePos);

        if ($call === null) {
            return null;
        }

        $arg = $this->argumentAt($call, $index, $paramName);

        if ($arg === null) {
            return null;
        }

        $read = $this->keyedRead($arg) ?? $this->readFromVariable($arg, $call, $ast);

        if ($read === null) {
            return null;
        }

        $type = ReceiverTypeResolver::resolve($read['recv'], new FileAst($ast, $uses, $namespace), $call);

        if ($type === null) {
            return null;
        }

        $accessor = $this->nativeAccessor($type, $family);

        return $accessor === null ? null : ['accessor' => $accessor, 'key' => $read['key']];
    }

    /**
     * Parse a caller file (cached), returning [ast, uses, namespace] or false.
     *
     * @return array{0: array<Node>, 1: array<string, string>, 2: ?string}|false
     */
    private function parseCaller(string $path): array|false
    {
        if (! array_key_exists($path, $this->callerCache)) {
            $content = @file_get_contents($path);
            $ast = $content === false ? null : $this->parse($content);

            $this->callerCache[$path] = $ast === null
                ? false
                : [$ast, FileImports::of($ast), $this->getNamespace($ast)];
        }

        return $this->callerCache[$path];
    }

    /**
     * The call expression whose start byte offset is $pos, or null.
     *
     * @param  array<Node>  $ast
     */
    private function callAt(array $ast, int $pos): ?Expr
    {
        $node = (new NodeFinder)->findFirst($ast, static fn (Node $n): bool =>
            ($n instanceof Expr\MethodCall
                || $n instanceof Expr\StaticCall
                || $n instanceof Expr\FuncCall
                || $n instanceof Expr\NullsafeMethodCall
                || $n instanceof Expr\New_)
            && (int) $n->getStartFilePos() === $pos);

        return $node instanceof Expr ? $node : null;
    }

    /**
     * The argument expression a call passes in position $index — matched by name
     * first (so named-argument syntax resolves correctly), else positionally.
     *
     * @param  Expr\MethodCall|Expr\StaticCall|Expr\FuncCall|Expr\NullsafeMethodCall|Expr\New_  $call
     */
    private function argumentAt(Expr $call, int $index, string $paramName): ?Expr
    {
        foreach ($call->args as $arg) {
            if ($arg instanceof Node\Arg && $arg->name instanceof Node\Identifier && $arg->name->toString() === $paramName) {
                return $arg->value;
            }
        }

        $positional = $call->args[$index] ?? null;

        return $positional instanceof Node\Arg && $positional->name === null ? $positional->value : null;
    }

    private function interprocMessage(string $param, string $accessor, string $key, array $sites): string
    {
        $where = count($sites) === 1
            ? "the call site ({$sites[0]})"
            : count($sites) . ' call sites (' . implode(', ', array_slice($sites, 0, 3)) . (count($sites) > 3 ? ', …' : '') . ')';

        return sprintf(
            'Parameter `$%s` is fed an untyped bag `get()` from %s, then coerced/guarded here — that re-implements the bag\'s native `%s(%s)`. Pass the typed accessor at the call site(s) and drop this guard (and the parameter can be typed).',
            $param,
            $where,
            $accessor,
            var_export($key, true),
        );
    }

    /**
     * The keyed read a variable was assigned from in its enclosing function
     * (`$raw = $request->get('payload')`), or null.
     *
     * @param  array<Node>  $ast
     * @return array{recv: Expr, key: string, default: ?Expr, getter: string}|null
     */
    private function readFromVariable(Expr $expr, Node $context, array $ast): ?array
    {
        if (! $expr instanceof Expr\Variable || ! is_string($expr->name)) {
            return null;
        }

        $fn = ReceiverTypeResolver::enclosingFunction($context, $ast);

        if ($fn === null) {
            return null;
        }

        $best = null;
        $bestPos = -1;

        // The nearest assignment to $expr BEFORE the use wins, so a later
        // reassignment (`$x = $request->get(...); $x = something();`) correctly
        // disqualifies the trace rather than matching the stale read.
        foreach ((new NodeFinder)->findInstanceOf((array) $fn->getStmts(), Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable
                || $assign->var->name !== $expr->name
                || $assign->getEndFilePos() >= $context->getStartFilePos()
            ) {
                continue;
            }

            $pos = (int) $assign->getEndFilePos();

            if ($pos > $bestPos) {
                $bestPos = $pos;
                $best = $this->keyedRead($assign->expr);
            }
        }

        return $best;
    }

    /**
     * If $node reads a string key off a receiver (`$r->get('k')`,
     * `$r->input('k')`, `$r['k']`), the receiver expr + key + optional default.
     *
     * @return array{recv: Expr, key: string, default: ?Expr, getter: string}|null
     */
    private function keyedRead(Expr $node): ?array
    {
        if ($node instanceof Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), self::UNTYPED_GETTERS, true)
        ) {
            $key = $node->args[0] ?? null;

            if ($key instanceof Node\Arg && $key->value instanceof Node\Scalar\String_) {
                $default = isset($node->args[1]) && $node->args[1] instanceof Node\Arg ? $node->args[1]->value : null;

                return ['recv' => $node->var, 'key' => $key->value->value, 'default' => $default, 'getter' => $node->name->toString()];
            }
        }

        if ($node instanceof Expr\ArrayDimFetch
            && $node->dim instanceof Node\Scalar\String_
            && ($node->var instanceof Expr\Variable || $node->var instanceof Expr\PropertyFetch)
        ) {
            return ['recv' => $node->var, 'key' => $node->dim->value, 'default' => null, 'getter' => 'get'];
        }

        return null;
    }

    /**
     * Resolve the receiver type, confirm it natively exposes the accessor for
     * $family, and build the finding — or null (the receiver is not a typed bag,
     * or is unresolvable: stay silent).
     *
     * @param  array{recv: Expr, key: string, default: ?Expr, getter: string}  $read
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     * @return array{line: int, family: string, key: string, accessor: string, message: string, autoFixable: bool}|null
     */
    private function resolve(array $read, string $family, Node $site, array $ast, array $uses, ?string $namespace, string $content, bool $autoFixable): ?array
    {
        $type = ReceiverTypeResolver::resolve($read['recv'], new FileAst($ast, $uses, $namespace), $site);

        if ($type === null) {
            return null;
        }

        $accessor = $this->nativeAccessor($type, $family);

        if ($accessor === null) {
            return null;
        }

        $recvSrc = $this->source($content, $read['recv']);

        return [
            'line' => $site->getStartLine(),
            'family' => $family,
            'key' => $read['key'],
            'accessor' => $accessor,
            'message' => $this->message($recvSrc, $accessor, $read['key'], $type, $site instanceof Expr\Ternary),
            'autoFixable' => $autoFixable,
        ];
    }

    private function message(string $recv, string $accessor, string $key, string $type, bool $isGuard): string
    {
        $short = $this->shortName($type);

        if ($isGuard) {
            return sprintf(
                'This `is_…() ? … : default` guard re-implements `%s::%s()`, which %s already provides — collapse it (and any intermediate variable) to `%s->%s(%s)`; the accessor returns a guaranteed value, so the guard is dead weight.',
                $short,
                $accessor,
                $short,
                $recv,
                $accessor,
                var_export($key, true),
            );
        }

        return sprintf(
            'This coerces an untyped `get()` that `%s` already types — `%s` exposes `%s(%s)`, so call `%s->%s(%s)` directly instead of casting/coercing the `mixed` by hand.',
            $short,
            $short,
            $accessor,
            var_export($key, true),
            $recv,
            $accessor,
            var_export($key, true),
        );
    }

    /**
     * The native accessor method name for $family on $type — but only when the
     * type is STRUCTURALLY a typed bag (it exposes the accessor family via
     * reflection). Returns null for a non-bag or an unloadable type (silent).
     */
    private function nativeAccessor(string $type, string $family): ?string
    {
        if (! class_exists($type) && ! interface_exists($type)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($type);
        } catch (ReflectionException) {
            return null;
        }

        $presentFamilies = 0;
        $target = null;

        foreach (self::ACCESSOR_NAMES as $fam => $names) {
            $name = $this->accessorMethod($reflection, $names);

            if ($name === null) {
                continue;
            }

            $presentFamilies++;

            if ($fam === $family) {
                $target = $name;
            }
        }

        // Structural gate: a lone same-named getter on a DTO is not a typed bag.
        // A genuine bag exposes the whole keyed-accessor family.
        return $presentFamilies >= 3 ? $target : null;
    }

    /**
     * The first of $names that exists on $reflection as a public, non-static
     * method taking at least one (key) argument.
     *
     * @param  list<string>  $names
     */
    private function accessorMethod(ReflectionClass $reflection, array $names): ?string
    {
        foreach ($names as $name) {
            if (! $reflection->hasMethod($name)) {
                continue;
            }

            $method = $reflection->getMethod($name);

            if ($method->isPublic() && ! $method->isStatic() && $method->getNumberOfParameters() >= 1) {
                return $name;
            }
        }

        return null;
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

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $namespace = $this->getNamespace($ast);
        $uses = FileImports::of($ast);
        $finder = new NodeFinder;
        $edits = [];
        $penance = [];

        // Only the coercer + cast forms rewrite in place (the guard-ternary form
        // is advisory — its fix removes a now-dead variable, left to the author).
        foreach ($finder->find($ast, fn (Node $n): bool => $this->coercionWrapper($n, $uses, $namespace) !== null) as $node) {
            $wrap = $this->coercionWrapper($node, $uses, $namespace);
            $read = $this->keyedRead($wrap['inner']);

            if ($read === null) {
                continue;
            }

            $type = ReceiverTypeResolver::resolve($read['recv'], new FileAst($ast, $uses, $namespace), $node);

            if ($type === null) {
                continue;
            }

            $accessor = $this->nativeAccessor($type, $wrap['family']);

            if ($accessor === null) {
                continue;
            }

            $replacement = sprintf(
                '%s->%s(%s%s)',
                $this->source($content, $read['recv']),
                $accessor,
                var_export($read['key'], true),
                $this->defaultArg($read['default'], $wrap['family'], $content),
            );

            $edits[] = ['start' => (int) $node->getStartFilePos(), 'end' => (int) $node->getEndFilePos(), 'text' => $replacement];
            $penance[] = sprintf('Replaced a hand-rolled coercion with the native accessor %s->%s()', $this->source($content, $read['recv']), $accessor);
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * The `, <default>` suffix for the accessor call — empty when the original
     * read had no default or its default is the family's natural zero.
     */
    private function defaultArg(?Expr $default, string $family, string $content): string
    {
        if ($default === null) {
            return '';
        }

        $src = trim($this->source($content, $default));

        if (in_array(strtolower($src), self::FAMILY_ZEROS[$family] ?? [], true)) {
            return '';
        }

        return ', ' . $src;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    private function source(string $content, Node $node): string
    {
        return substr($content, (int) $node->getStartFilePos(), (int) $node->getEndFilePos() - (int) $node->getStartFilePos() + 1);
    }

    private function sameValue(Expr $a, Expr $b): bool
    {
        if ($a instanceof Expr\Variable && $b instanceof Expr\Variable) {
            return $a->name === $b->name;
        }

        $ra = $this->keyedRead($a);
        $rb = $this->keyedRead($b);

        return $ra !== null && $rb !== null
            && $ra['key'] === $rb['key']
            && $ra['getter'] === $rb['getter'];
    }
}
