<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
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
class NoContainerResolutionProphet extends PhpCommandment
{
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
resolved by the container — i.e. nobody constructs it with `new`.
Before moving the call to the constructor, search for `new YourClass(`
across the codebase. If there are no matches, the class is DI-resolved
and constructor injection is safe. If there are matches, fix those
call sites first (or accept that this particular call has to stay).

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

        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        // app(X) and resolve(X) — bare function calls.
        /** @var array<Node\Expr\FuncCall> $funcCalls */
        $funcCalls = $finder->findInstanceOf($ast, Node\Expr\FuncCall::class);

        foreach ($funcCalls as $call) {
            if (! $call->name instanceof Node\Name) {
                continue;
            }

            $fnName = $call->name->toString();

            if ($fnName === 'app' && ! empty($call->args)) {
                $warning = $this->buildWarning('app', $call, $content);
            } elseif ($fnName === 'resolve' && ! empty($call->args)) {
                $warning = $this->buildWarning('resolve', $call, $content);
            } else {
                continue;
            }

            $this->addUnique($warnings, $seen, $warning);
        }

        // app()->make(X) and app()->makeWith(X, ...) — chained method calls.
        /** @var array<Node\Expr\MethodCall> $methodCalls */
        $methodCalls = $finder->findInstanceOf($ast, Node\Expr\MethodCall::class);

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

            $warning = $this->buildWarning('app()->make', $call, $content);
            $this->addUnique($warnings, $seen, $warning);
        }

        // App::make(X) and App::makeWith(X, ...) — facade static calls.
        /** @var array<Node\Expr\StaticCall> $staticCalls */
        $staticCalls = $finder->findInstanceOf($ast, Node\Expr\StaticCall::class);

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

            $warning = $this->buildWarning('App::make', $call, $content);
            $this->addUnique($warnings, $seen, $warning);
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

    private function buildWarning(string $shape, Node\Expr $call, string $content): ?Warning
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
            '%s — inject %s via the constructor of the containing class instead.',
            $callShape,
            $targetLabel,
        );

        $message .= ' Verify the containing class is itself resolved by Laravel: search for'
            . ' `new <ContainingClass>(` — if there are no matches, the class is DI-resolved'
            . ' and constructor injection works there too. If it is constructed manually,'
            . ' fix those call sites first or keep this resolution.';

        return Warning::at($line, $message, $snippet);
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
