<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\DevTools\RequestRecorder;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Inertia\TypedSharedPropsRecorder;

/**
 * Overriding a VENDOR binding: the abstract is a package's own contract, resolved from that package's
 * code, and this registration swaps its default for the shop's subclass. Nothing first-party can ever
 * name the abstract — which is what makes the binding load-bearing rather than dead — so a scan that
 * reads only first-party files must not call it orphaned.
 */
class DevToolsServiceProvider extends ServiceProvider
{
    #[Righteous(OrphanedBinding::class)]
    public function register(): void
    {
        $this->app->bind(RequestRecorder::class, TypedSharedPropsRecorder::class);
    }
}
