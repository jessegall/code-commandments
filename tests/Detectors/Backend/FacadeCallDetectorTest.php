<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\FacadeCallDetector;
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
}
