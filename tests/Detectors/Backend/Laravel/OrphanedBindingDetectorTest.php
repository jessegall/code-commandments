<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\OrphanedBindingDetector;
use PHPUnit\Framework\TestCase;

final class OrphanedBindingDetectorTest extends TestCase
{
    /** @return list<int> the 1-based lines flagged */
    private function lines(string $code): array
    {
        return array_map(
            static fn ($m): int => $m->line(),
            (new OrphanedBindingDetector)->find(Codebase::fromString($code)),
        );
    }

    public function test_a_sibling_factory_resolving_the_abstract_is_real_demand(): void
    {
        // The registry pattern: config names a provider and a SECOND binding's factory resolves the
        // registry to turn that name into an implementation. The registry is load-bearing — deleting
        // it breaks every Provider resolution — but the only reference to it sits inside another
        // binding's closure. Only a reference inside the registration of the SAME abstract proves
        // nothing; a different binding reaching for it is demand like any other.
        $code = <<<'PHP'
        <?php
        namespace App;

        class Registry {}
        class Provider {}
        class Impl {}
        class Unused {}

        class OAuthServiceProvider {
            public function register(): void {
                $this->app->singleton(Registry::class, function ($app) {
                    $registry = new Registry();
                    $registry->register('builtin', fn () => $app->make(Impl::class));

                    return $registry;
                });

                $this->app->bind(Provider::class, function ($app) {
                    return $app->make(Registry::class)->get('builtin');
                });

                $this->app->singleton(Unused::class, fn () => new Unused());
            }
        }

        class Consumer {
            public function __construct(private readonly Provider $provider) {}
        }
        PHP;

        // Registry: resolved by Provider's factory. Impl: resolved by Registry's factory. Provider:
        // type-hinted. Unused: named nowhere but its own registration — the only dead wiring here.
        $this->assertSame([22], $this->lines($code));
    }

    public function test_flags_only_the_binding_nothing_resolves(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        interface Mailer {}
        interface Probe {}
        interface Locator {}
        class SmtpMailer implements Mailer {}
        class PortProbe implements Probe {}
        class DbLocator implements Locator {}

        class AppServiceProvider {
            public function register(): void {
                $this->app->singleton(Mailer::class, fn () => new SmtpMailer());
                $this->app->singleton(Probe::class, fn () => new PortProbe());
                $this->app->bind(Locator::class, DbLocator::class);
            }
        }

        class Newsletter {
            public function __construct(private Mailer $mailer) {}
        }

        class Report {
            public function build(): void { app(Locator::class)->find(); }
        }
        PHP;

        // Mailer is constructor-injected, Locator is resolved via app() — Probe is asked for by nobody.
        $this->assertSame([14], $this->lines($code));
    }

    public function test_an_implementation_is_supply_not_demand(): void
    {
        // The real shape from a consumer: the abstract still HAS implementations, and a docblock still
        // points at it, but nothing ever asks the container for it. An `implements` clause is the
        // supply side of the wiring — counting it would make every dead abstract look live.
        $code = <<<'PHP'
        <?php
        namespace App;

        interface ResourceCatalog {}
        class RegistryBackedResourceCatalog implements ResourceCatalog {}

        /** Default {@see ResourceCatalog} returning no hints. */
        class NullResourceCatalog implements ResourceCatalog {}

        class AssistantServiceProvider {
            public function register(): void {
                $this->app->bindIf(ResourceCatalog::class, RegistryBackedResourceCatalog::class);
            }
        }
        PHP;

        $this->assertSame([12], $this->lines($code));
    }

    public function test_a_factory_building_what_it_binds_is_not_demand(): void
    {
        // `singleton(X::class, fn () => new X())` names X twice inside its OWN registration. That is
        // the wiring talking to itself and proves nothing about demand, so the binding still counts as
        // orphaned — otherwise every self-building singleton would be permanently invisible.
        $code = <<<'PHP'
        <?php
        namespace App;

        class EventRegistry {
            public function event(string $e): self { return $this; }
        }

        class EditorServiceProvider {
            public function register(): void {
                $this->app->singleton(EventRegistry::class, static fn (): EventRegistry => new EventRegistry()->event('moved'));
            }
        }
        PHP;

        $this->assertSame([10], $this->lines($code));
    }

    public function test_an_import_alone_never_counts_as_a_consumer(): void
    {
        // Every consumer file lists the class at the top. If a `use` import counted as demand, the
        // provider's own import would keep its binding alive and the rule would be permanently inert.
        $code = <<<'PHP'
        <?php
        namespace App\Providers {
            use App\Contracts\Reporter;
            use App\Support\NullReporter;

            class ReportServiceProvider {
                public function register(): void {
                    $this->app->singleton(Reporter::class, NullReporter::class);
                }
            }
        }
        namespace App\Contracts { interface Reporter {} }
        namespace App\Support { class NullReporter implements \App\Contracts\Reporter {} }
        PHP;

        $this->assertSame([8], $this->lines($code));
    }

    public function test_a_string_key_consumer_keeps_a_binding_alive(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        interface Clock {}
        class SystemClock implements Clock {}

        class ClockServiceProvider {
            public function register(): void {
                $this->app->singleton(Clock::class, SystemClock::class);
            }
        }

        class Scheduler {
            public function now(): mixed { return app('App\Clock'); }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }
}
