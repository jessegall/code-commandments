<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag runtime container resolution that should be constructor injection.
 *
 * `app(X::class)`, `app()->make(X::class)`, `App::make(X::class)`, and
 * `resolve(X::class)` are service-locator calls that hide the dependency
 * from the class signature. Anything resolved at the call site can almost
 * always be moved to the constructor — Laravel will resolve it once when
 * the containing class is built, and the dependency becomes visible.
 *
 * Always emitted as warnings (manual review), not sins:
 * - Some legitimate uses exist (lazy resolution to break cycles, deferred
 *   facades, fetching the application/config services from procedural code).
 * - The fix sometimes cascades — the containing class also has to be
 *   constructor-injectable, which the consumer should verify.
 *
 * Skipped:
 * - Service providers (`extends ServiceProvider`) — `register()` and
 *   `boot()` legitimately resolve from the container while wiring it up.
 * - `app()` with no arguments (returns the container; common in
 *   `app()->bind(...)` calls inside providers).
 * - `App::class` / `Application::class` arguments (fetching the app
 *   instance itself is rarely a constructor-injection candidate).
 */
#[IntroducedIn('1.9.0')]
class NoContainerResolutionProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $codebaseIndex = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->codebaseIndex = $index;
    }

    /**
     * Class arguments that are noisy to flag — fetching the application
     * or container instance itself isn't the smell this prophet targets.
     */
    private const NEUTRAL_TARGETS = [
        'Illuminate\\Foundation\\Application',
        'Illuminate\\Contracts\\Foundation\\Application',
        'Illuminate\\Contracts\\Container\\Container',
        'Psr\\Container\\ContainerInterface',
    ];

    public function description(): string
    {
        return 'Prefer constructor injection over container resolution (app(), resolve(), App::make())';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Resolving services from the container at the call site — `app(Foo::class)`,
`resolve(Foo::class)`, `app()->make(Foo::class)`, `App::make(Foo::class)` —
is the service-locator pattern. The dependency is hidden from the class
signature, the class becomes harder to test (you have to bind into the
container instead of passing a mock), and the call site has to know how
to ask for the dependency.

Constructor injection makes the dependency explicit, lets the container
do the wiring once, and lets tests just pass instances directly.

Bad:
    class CreateInvoice
    {
        public function handle(Order $order): Invoice
        {
            $generator = app(InvoiceGenerator::class);

            return $generator->for($order);
        }
    }

Good:
    class CreateInvoice
    {
        public function __construct(
            private InvoiceGenerator $generator,
        ) {}

        public function handle(Order $order): Invoice
        {
            return $this->generator->for($order);
        }
    }

Tracking the origin
-------------------
Constructor injection only works if the *containing* class is itself
resolved by the container — i.e. nobody constructs it with `new`. The
prophet uses the same scroll-wide call graph that other cross-file
prophets rely on to answer this for you:

- If no `new ContainingClass(...)` exists anywhere in the scroll, the
  warning says so plainly — move the dependency to the constructor.
- If one exists, the warning points at the file:line so you can fix
  the call site first (or accept that this resolution has to stay).
- In single-file mode (`--file`) or when the containing class lives
  outside the scanned scroll, the prophet falls back to a "verify
  manually" hint instead of guessing.

Reported as a warning, not a sin
--------------------------------
A few legitimate uses exist (lazy resolution to break circular
dependencies, deferred facades, fetching `Application` itself from
procedural code), and the fix may cascade. So this is surfaced for
review rather than failing the run.

Skipped automatically:
- Service providers (`extends ServiceProvider`) — wiring the container
  *is* the job there.
- `app()` with no arguments — returns the container, common with
  `app()->bind(...)` style calls.
- Resolving `Application` / `Container` itself.

Known intentional exception: class-string registry loops
--------------------------------------------------------
The rule still emits a warning here, but if you see this exact shape,
leave the code as-is — it is deliberate, not service-locator misuse:

    private array $normalizers = [
        CommaSeparatedArrayNormalizer::class,
        StringToArrayNormalizer::class,
    ];

    private function normalizeValue(...): mixed
    {
        foreach ($this->normalizers as $normalizerClass) {
            $normalizer = app($normalizerClass);
            // ...
        }
    }

The call qualifies as a registry loop when ALL of the following hold:

  1. The argument to `app()` / `resolve()` / `App::make()` is a variable
     (not a literal `Foo::class`).
  2. That variable is the loop variable of a `foreach`.
  3. The `foreach` iterates either a local array literal of `::class`
     items, or a class property typed `array<class-string<...>>` whose
     default is an array of `::class` items.

Why this is intentional:
- The array IS the registry — adding an entry is one line, not a
  constructor edit and a binding update.
- Resolution is lazy — only the matching strategy is instantiated per
  call, instead of eagerly building every entry per request.
- Constructor-injecting every strategy defeats the point of the list
  and leaks the registry's contents into the dependency graph.

Do NOT "fix" this warning by:
- Injecting every strategy into the constructor.
- Replacing the registry array with constructor parameters.

Acceptable responses:
- Leave the code as-is and ignore the warning.
- Absolve the file (`commandments judge --absolve`) once reviewed.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        if ($this->isLaravelClass($ast, 'provider')) {
            return $this->righteous();
        }

        $namespace = $this->getNamespace($ast);
        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        /** @var array<Node\Stmt\Class_> $classes */
        $classes = $finder->findInstanceOf($ast, Node\Stmt\Class_::class);

        // Walk per class so each match knows its containing class FQCN.
        // Calls outside any class (top-level scripts) are walked once with
        // a null containing FQCN.
        $scopes = [];

        foreach ($classes as $class) {
            if ($class->name === null) {
                continue;
            }

            $shortName = $class->name->toString();
            $fqcn = $namespace !== null ? $namespace . '\\' . $shortName : $shortName;
            $scopes[] = ['fqcn' => $fqcn, 'short' => $shortName, 'nodes' => $class->stmts ?? []];
        }

        if ($scopes === []) {
            $scopes[] = ['fqcn' => null, 'short' => null, 'nodes' => $ast];
        }

        foreach ($scopes as $scope) {
            $context = ['fqcn' => $scope['fqcn'], 'short' => $scope['short']];

            // app(X) and resolve(X) — bare function calls.
            /** @var array<Node\Expr\FuncCall> $funcCalls */
            $funcCalls = $finder->findInstanceOf($scope['nodes'], Node\Expr\FuncCall::class);

            foreach ($funcCalls as $call) {
                if (! $call->name instanceof Node\Name) {
                    continue;
                }

                $fnName = $call->name->toString();

                if ($fnName === 'app' && ! empty($call->args)) {
                    $warning = $this->buildWarning('app', $call, $content, $context);
                } elseif ($fnName === 'resolve' && ! empty($call->args)) {
                    $warning = $this->buildWarning('resolve', $call, $content, $context);
                } else {
                    continue;
                }

                $this->addUnique($warnings, $seen, $warning);
            }

            // app()->make(X) and app()->makeWith(X, ...).
            /** @var array<Node\Expr\MethodCall> $methodCalls */
            $methodCalls = $finder->findInstanceOf($scope['nodes'], Node\Expr\MethodCall::class);

            foreach ($methodCalls as $call) {
                if (! $this->isResolveMethod($call->name)) {
                    continue;
                }

                if (! $this->isAppFuncCall($call->var)) {
                    continue;
                }

                if (empty($call->args)) {
                    continue;
                }

                $warning = $this->buildWarning('app()->make', $call, $content, $context);
                $this->addUnique($warnings, $seen, $warning);
            }

            // App::make(X) and App::makeWith(X, ...).
            /** @var array<Node\Expr\StaticCall> $staticCalls */
            $staticCalls = $finder->findInstanceOf($scope['nodes'], Node\Expr\StaticCall::class);

            foreach ($staticCalls as $call) {
                if (! $this->isResolveMethod($call->name)) {
                    continue;
                }

                if (! $call->class instanceof Node\Name) {
                    continue;
                }

                if (! $this->isAppFacade($call->class)) {
                    continue;
                }

                if (empty($call->args)) {
                    continue;
                }

                $warning = $this->buildWarning('App::make', $call, $content, $context);
                $this->addUnique($warnings, $seen, $warning);
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return new Judgment(warnings: $warnings);
    }

    /**
     * @param  array<Warning>  $warnings
     * @param  array<string, true>  $seen
     */
    private function addUnique(array &$warnings, array &$seen, ?Warning $warning): void
    {
        if ($warning === null) {
            return;
        }

        $key = $warning->line . '|' . $warning->message;

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $warnings[] = $warning;
    }

    /**
     * @param  array{fqcn: ?string, short: ?string}  $context
     */
    private function buildWarning(string $shape, Node\Expr $call, string $content, array $context): ?Warning
    {
        $args = $this->getCallArgs($call);

        if ($args === []) {
            return null;
        }

        $target = $this->describeTarget($args[0]);

        if ($target !== null && in_array($target['fqcn'] ?? '', self::NEUTRAL_TARGETS, true)) {
            return null;
        }

        $line = $call->getStartLine();
        $snippet = $this->getLineSnippet($content, $line);
        $callShape = $this->renderCallShape($shape, $target);
        $targetLabel = $target['display'] ?? 'a service';

        $message = sprintf(
            '%s — inject %s via the constructor of %s instead.',
            $callShape,
            $targetLabel,
            $context['short'] !== null ? $context['short'] : 'the containing class',
        );

        $message .= ' ' . $this->originHint($context['fqcn']);

        return Warning::at($line, $message, $snippet);
    }

    /**
     * Build the second sentence of the warning, hinting at whether the
     * containing class can actually take a constructor dependency. Uses
     * the cross-file index when available; falls back to a generic prompt
     * to inspect manually.
     */
    private function originHint(?string $containingFqcn): string
    {
        $shortName = $containingFqcn !== null ? $this->shortName($containingFqcn) : '<ContainingClass>';

        if ($this->codebaseIndex === null || $containingFqcn === null) {
            return sprintf(
                'Verify the containing class is itself DI-resolved (search for `new %s(` — no matches means it is, so constructor injection works there too).',
                $shortName,
            );
        }

        // The index only tracks classes it actually saw being defined.
        // If the containing class isn't there (e.g. excluded path, vendor),
        // we have no authority to claim "never `new`d".
        if ($this->codebaseIndex->classByFqcn($containingFqcn) === null) {
            return 'Containing class is outside the scanned scroll — verify manually that it is DI-resolved before moving the dependency.';
        }

        $sites = $this->codebaseIndex->instantiationsOf($containingFqcn);

        if ($sites === []) {
            return 'No `new ' . $this->shortName($containingFqcn) . '(` was found in the scroll, so the class is DI-resolved — move the dependency to the constructor.';
        }

        $sample = $sites[0];
        $relative = basename(dirname($sample['file'])) . '/' . basename($sample['file']);
        $extra = count($sites) > 1 ? sprintf(' (+%d more)', count($sites) - 1) : '';

        return sprintf(
            '`new %s(` is constructed manually at %s:%d%s — fix those call sites first or accept that this resolution stays.',
            $this->shortName($containingFqcn),
            $relative,
            $sample['line'],
            $extra,
        );
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /**
     * @return array<Node\Arg>
     */
    private function getCallArgs(Node\Expr $call): array
    {
        if ($call instanceof Node\Expr\FuncCall
            || $call instanceof Node\Expr\MethodCall
            || $call instanceof Node\Expr\StaticCall) {
            return array_values(array_filter(
                $call->args,
                static fn ($arg) => $arg instanceof Node\Arg,
            ));
        }

        return [];
    }

    /**
     * @return array{display: string, fqcn: ?string}|null
     */
    private function describeTarget(Node\Arg $arg): ?array
    {
        $value = $arg->value;

        if ($value instanceof Node\Expr\ClassConstFetch
            && $value->class instanceof Node\Name
            && $value->name instanceof Node\Identifier
            && $value->name->toString() === 'class') {
            $name = $value->class;
            $short = $name->getLast();
            $fqcn = $name->isFullyQualified() ? ltrim($name->toString(), '\\') : $name->toString();

            return [
                'display' => $short,
                'fqcn' => $fqcn,
            ];
        }

        if ($value instanceof Node\Scalar\String_) {
            return [
                'display' => sprintf("'%s'", $value->value),
                'fqcn' => null,
            ];
        }

        return [
            'display' => 'the resolved service',
            'fqcn' => null,
        ];
    }

    /**
     * @param  array{display: string, fqcn: ?string}|null  $target
     */
    private function renderCallShape(string $shape, ?array $target): string
    {
        $arg = $target['display'] ?? 'X';

        return match ($shape) {
            'app' => sprintf('app(%s)', $this->classExpr($arg, $target)),
            'resolve' => sprintf('resolve(%s)', $this->classExpr($arg, $target)),
            'app()->make' => sprintf('app()->make(%s)', $this->classExpr($arg, $target)),
            'App::make' => sprintf('App::make(%s)', $this->classExpr($arg, $target)),
            default => $shape,
        };
    }

    /**
     * @param  array{display: string, fqcn: ?string}|null  $target
     */
    private function classExpr(string $display, ?array $target): string
    {
        if ($target === null) {
            return $display;
        }

        if ($target['fqcn'] !== null) {
            return $display . '::class';
        }

        return $display;
    }

    private function isResolveMethod(Node\Identifier|Node\Expr $name): bool
    {
        if (! $name instanceof Node\Identifier) {
            return false;
        }

        return $name->toString() === 'make' || $name->toString() === 'makeWith';
    }

    private function isAppFuncCall(Node\Expr $expr): bool
    {
        if (! $expr instanceof Node\Expr\FuncCall) {
            return false;
        }

        if (! $expr->name instanceof Node\Name) {
            return false;
        }

        return $expr->name->toString() === 'app';
    }

    private function isAppFacade(Node\Name $class): bool
    {
        $name = ltrim($class->toString(), '\\');

        if ($name === 'App') {
            return true;
        }

        return $name === 'Illuminate\\Support\\Facades\\App';
    }

    private function getLineSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
