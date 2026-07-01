<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\FacadeCall;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\EloquentCast;
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
 *
 * An Eloquent CAST is exempt too: Eloquent `new`-instantiates a cast (`'col' =>
 * MyCast::class`) — there is no container and no constructor to inject into — so a
 * facade (`Crypt::`, exactly as Laravel's own `encrypted` cast does) is the only way
 * to reach a service. Detected by the cast contract the class implements, not a name.
 *
 * A call OUTSIDE any class is exempt by the same logic: a route file, a config
 * script, `bootstrap/app.php`, a global helper — there is no class and no
 * constructor to inject into, so a facade is the idiom. Detected by the absence of
 * an enclosing class, not by the file's path or name.
 *
 * A `::fake()` call is exempt: `Mail::fake()`/`Bus::fake()`/`Event::fake()` install a
 * test double by swapping the container binding (`static::swap(new …Fake)`). There is
 * no instance/contract form — `fake()` exists ONLY as the facade static — so there is
 * nothing to inject; relocating it merely launders the same call. Same rationale as the
 * provider exemption: the facade IS the sanctioned mechanism for installing the double.
 */
final class FacadeCallDetector implements Detector
{
    private const string FACADE_NAMESPACE = 'Illuminate\\Support\\Facades\\';

    private const string SERVICE_PROVIDER = 'Illuminate\\Support\\ServiceProvider';

    public function sin(): Sin
    {
        return new FacadeCall();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(static fn (AstNode $node): bool => $node->staticCallClassStartsWith(self::FACADE_NAMESPACE))
            ->reject(static fn (AstNode $node): bool => $node->staticCallMethodIs('fake'))
            ->reject(static fn (AstNode $node): bool => $node->isOutsideClass())
            ->reject(static fn (AstNode $node): bool => $codebase->extends($node->enclosingClassName(), self::SERVICE_PROVIDER))
            ->reject(static fn (AstNode $node): bool => EloquentCast::is($codebase, $node->enclosingClassName()))
            ->get();
    }
}
