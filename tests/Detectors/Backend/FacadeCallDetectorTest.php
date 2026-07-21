<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\FacadeCallDetector;
use PHPUnit\Framework\TestCase;

final class FacadeCallDetectorTest extends TestCase
{
    public function test_flags_a_facade_static_call_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Cache;
        use App\Support\Money;

        class Service
        {
            public function cached(): string
            {
                return Cache::get('k', 'd');
            }

            public function priced(): Money
            {
                return Money::zero();
            }
        }
        PHP;

        $hits = (new FacadeCallDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Service::cached'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_a_service_provider_boot_seam_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Support { class ServiceProvider {} }
        namespace App {
            use Illuminate\Support\Facades\Route;
            use Illuminate\Support\Facades\Event;
            use Illuminate\Support\ServiceProvider;
            // wiring the framework at boot through facades is the provider's job
            class HttpServiceProvider extends ServiceProvider {
                public function boot(): void {
                    Route::middleware('web')->group(fn () => null);
                    Event::listen('x', 'y');
                }
            }
            // a plain service has nothing to wire — its facade reach IS a sin
            class Reporter {
                public function log(): void { Event::dispatch('x'); }
            }
        }
        PHP;

        $hits = (new FacadeCallDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Reporter::log'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_a_queued_jobs_framework_invoked_hooks_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Illuminate\Contracts\Queue { interface ShouldQueue {} }
        namespace App {
            use Illuminate\Contracts\Queue\ShouldQueue;
            use Illuminate\Support\Facades\App;
            use Illuminate\Support\Facades\Log;

            class RunWorkflowJob implements ShouldQueue {
                // the framework calls `$command->failed($e)` DIRECTLY — nothing to inject into
                public function failed(\Throwable $failure): void {
                    App::make('runs')->abandoned($failure);
                }

                // …but `handle()` is called by the CONTAINER, so its collaborators belong in its signature
                public function handle(): void {
                    Log::info('running');
                }
            }
        }
        PHP;

        $hits = (new FacadeCallDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\RunWorkflowJob::handle'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_testing_facade_fake_installers_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Mail;
        use Illuminate\Support\Facades\Bus;
        use Illuminate\Support\Facades\Cache;

        class SandboxRunner
        {
            public function run(): void
            {
                // ::fake() swaps the container binding — no injectable contract form…
                Mail::fake();
                Bus::fake();
                // …but an ordinary facade reach in the same method is STILL a sin.
                Cache::get('k');
            }
        }
        PHP;

        $hits = (new FacadeCallDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\SandboxRunner::run'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
