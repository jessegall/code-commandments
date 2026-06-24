<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Flag Laravel facade calls (`Log::`, `Cache::`, `Http::`, …) inside services.
 *
 * A facade is a global service locator wearing a static face: it hides the
 * dependency from the class signature and forces tests to swap a global. The
 * complement of NoContainerResolution (which covers `app()`/`resolve()`):
 * inject the underlying contract through the constructor instead.
 *
 *
 *
 * @method-generated-start
 * @method static allow(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.115.0')]
class NoFacadesInServicesProphet extends PhpCommandment
{
    private const FACADE_NAMESPACE = 'Illuminate\\Support\\Facades\\';

    /**
     * Facade short name => the contract to inject instead (for the message).
     */
    private const CONTRACTS = [
        'Log' => 'Psr\\Log\\LoggerInterface',
        'Cache' => 'Illuminate\\Contracts\\Cache\\Repository',
        'Config' => 'Illuminate\\Contracts\\Config\\Repository',
        'Queue' => 'Illuminate\\Contracts\\Queue\\Queue',
        'Mail' => 'Illuminate\\Contracts\\Mail\\Mailer',
        'Event' => 'Illuminate\\Contracts\\Events\\Dispatcher',
        'Bus' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
        'Redis' => 'Illuminate\\Contracts\\Redis\\Factory',
        'Storage' => 'Illuminate\\Contracts\\Filesystem\\Factory',
        'Http' => 'Illuminate\\Http\\Client\\Factory',
        'Notification' => 'Illuminate\\Contracts\\Notifications\\Dispatcher',
    ];

    public function description(): string
    {
        return 'Do not call Laravel facades in services — inject the underlying contract via the constructor';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class under src/ calls a Laravel facade (Log::, Cache::, Http::, Bus::, Queue::, Storage::, Mail::, …) — a global service locator that hides the dependency and forces tests to swap a global.')
            ->leaveWhen('the class is a service provider (wiring the container IS its job), a test, or the facade has no injectable contract and is genuinely procedural glue. Str::/Arr::/Carbon:: are plain helpers, not facades, and never fire.')
            ->whenUnsure('if the facade has a contract you can type-hint (LoggerInterface, the cache/queue/mailer repository, the HTTP client factory), inject it; if it is a one-off in a boundary class you do not own, leave it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A facade is the service-locator pattern with a static face. `Log::info(...)`
reaches a global, so the dependency never appears in the constructor, the class
can't be unit-tested without `Log::swap()`/`Log::fake()`, and the coupling to
the framework is invisible at the signature. Inside src/ services, inject the
underlying contract instead — the same fix NoContainerResolution asks for
`app()`/`resolve()`.

Bad — global facade:
    use Illuminate\Support\Facades\Log;

    class SyncOrders
    {
        public function handle(): void
        {
            Log::error('sync failed');
        }
    }

Good — injected contract:
    use Psr\Log\LoggerInterface;

    class SyncOrders
    {
        public function __construct(private LoggerInterface $log) {}

        public function handle(): void
        {
            $this->log->error('sync failed');
        }
    }

WHAT FIRES — any static call `X::method()` where `X` resolves to a class under
`Illuminate\Support\Facades\` (imported or fully-qualified). The fix is to
type-hint the contract: Log → LoggerInterface, Cache → cache Repository,
Queue → Queue, Mail → Mailer, Bus → bus Dispatcher, Http → the HTTP client
Factory, Storage → filesystem Factory, and so on.

WHAT DOES NOT — `Str::`, `Arr::`, `Carbon::`, `Number::` and other plain
`Illuminate\Support\*` helpers are not facades (no global state to inject) and
never fire. Service providers are skipped entirely — binding and resolving the
container is their whole job. `Facade::fake()`/`::swap()` in tests live outside
src/.

Reported as a warning — a few facades have no clean contract (or sit in glue
code you don't own); those are judgment calls. Set `severity => 'sin'` to block,
or `allow => ['DB']` to permit specific facades (e.g. `DB::transaction`).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Service providers legitimately wire the container.
        if ($this->isLaravelClass($ast, 'provider')) {
            return $this->righteous();
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->traverse($ast);

        $allow = $this->allowList();
        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        /** @var array<Node\Expr\StaticCall> $calls */
        $calls = $finder->findInstanceOf($ast, Node\Expr\StaticCall::class);

        foreach ($calls as $call) {
            if (! $call->class instanceof Node\Name) {
                continue;
            }

            $facade = $this->facadeShortName($call->class);

            if ($facade === null || in_array($facade, $allow, true)) {
                continue;
            }

            $method = $call->name instanceof Node\Identifier ? $call->name->toString() : 'method';
            $key = $facade . ':' . $method;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                $this->messageFor($facade, $method),
                $this->lineSnippet($content, $call->getStartLine()),
                'no-facade:' . $key,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The facade short name when $name resolves to an `Illuminate\Support\Facades\*`
     * class (via import or fully-qualified), otherwise null.
     */
    private function facadeShortName(Node\Name $name): ?string
    {
        $resolved = $name->getAttribute('resolvedName');
        $fqcn = $resolved instanceof Node\Name ? $resolved->toString() : ltrim($name->toString(), '\\');

        if (! str_starts_with($fqcn, self::FACADE_NAMESPACE)) {
            return null;
        }

        return substr($fqcn, strlen(self::FACADE_NAMESPACE));
    }

    private function messageFor(string $facade, string $method): string
    {
        $contract = self::CONTRACTS[$facade] ?? null;

        $inject = $contract !== null
            ? sprintf('inject %s via the constructor and call $this->…->%s()', $this->shortName($contract), $method)
            : sprintf('inject the underlying service via the constructor instead of %s::%s()', $facade, $method);

        return sprintf('%s::%s() is a facade (global service locator) — %s.', $facade, $method, $inject);
    }

    /**
     * @return list<string>
     */
    private function allowList(): array
    {
        $allow = $this->config('allow', []);

        return is_array($allow) ? array_values(array_map('strval', $allow)) : [];
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
