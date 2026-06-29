<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A Laravel facade call — `Cache::get(...)`, `Log::info(...)`, `Mail::raw(...)`.
 * A facade is a global reach into the container wearing a static-method face: it
 * hides the dependency, can't be substituted, and ties the class to the
 * framework. Inject the underlying contract instead. Points at laravel-idioms.
 *
 * The signal is the framework's own facade namespace, resolved from the file's
 * imports — not a hand-kept list of facade names.
 *
 * A `ServiceProvider` is exempt: wiring the framework at boot (routes, event
 * listeners, bindings) through facades is the provider's sanctioned job, and a
 * provider's `register()`/`boot()` has nothing to inject into.
 */
final class FacadeCallDetector implements Detector
{
    private const string FACADE_NAMESPACE = 'Illuminate\\Support\\Facades\\';

    private const string SERVICE_PROVIDER = 'Illuminate\\Support\\ServiceProvider';

    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(static fn (AstNode $node): bool => str_starts_with($node->staticCallClass() ?? '', self::FACADE_NAMESPACE))
            ->reject(static fn (AstNode $node): bool => $codebase->extends($node->enclosingClassName(), self::SERVICE_PROVIDER))
            ->get();
    }
}
