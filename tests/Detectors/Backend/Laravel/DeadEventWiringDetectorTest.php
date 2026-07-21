<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\DeadEventWiringDetector;
use PHPUnit\Framework\TestCase;

final class DeadEventWiringDetectorTest extends TestCase
{
    /** @return list<int> */
    private function lines(string $code): array
    {
        return array_map(
            static fn ($m): int => $m->line(),
            (new DeadEventWiringDetector)->find(Codebase::fromString($code)),
        );
    }

    public function test_flags_only_the_listener_nothing_can_fire(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Event;

        class OrderShipped {}
        class OrderPaid {}
        class LegacyImported {}
        class Listener {}

        class AppServiceProvider {
            public function boot(): void {
                Event::listen(OrderShipped::class, Listener::class);
                Event::listen(OrderPaid::class, Listener::class);
                Event::listen(LegacyImported::class, Listener::class);
            }
        }

        class Shipper {
            public function ship(): void { event(new OrderShipped()); }
        }

        class Payer {
            public function pay(): void { OrderPaid::dispatch(); }
        }
        PHP;

        // OrderShipped is constructed, OrderPaid is dispatched statically — LegacyImported is neither.
        $this->assertSame([15], $this->lines($code));
    }

    public function test_a_fired_subclass_keeps_its_base_events_wiring_alive(): void
    {
        // A listener bound to a base event answers every child, so firing the child is what keeps the
        // base registration meaningful. Ignoring inheritance here would flag every marker-event listener.
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Event;

        class TriggersWorkflows {}
        class ProductSaved extends TriggersWorkflows {}
        class Listener {}

        class AppServiceProvider {
            public function boot(): void {
                Event::listen(TriggersWorkflows::class, Listener::class);
            }
        }

        class Saver {
            public function save(): void { event(new ProductSaved()); }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_a_framework_event_is_never_judged(): void
    {
        // `JobQueued` is raised inside the framework, which the scan never sees. Its absence here proves
        // nothing, so only events DECLARED in the scanned codebase are judged. Flagging these was the
        // detector's first false positive on a real app.
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Queue\Events\JobQueued;
        use Illuminate\Support\Facades\Event;

        class BackupServiceProvider {
            public function boot(): void {
                Event::listen(JobQueued::class, function (JobQueued $event) {
                    $this->count($event);
                });
            }

            private function count(object $event): void {}
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_a_listener_bound_to_an_interface_is_an_extension_point(): void
    {
        // A package listens on the CONTRACT its host applications implement on their own domain events.
        // The implementors live outside the scanned tree by design, so "nothing here fires it" proves
        // nothing — the registration is the package's public trigger API, not dead wiring.
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Event;

        interface TriggersWorkflowsEvent {}
        class RunWorkflowsForEvent {}

        class TriggersServiceProvider {
            public function boot(): void {
                Event::listen(TriggersWorkflowsEvent::class, RunWorkflowsForEvent::class);
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_a_dynamically_named_event_is_not_judged(): void
    {
        // `Event::listen($event, …)` over a discovered class list resolves to nothing statically.
        $code = <<<'PHP'
        <?php
        namespace App;

        use Illuminate\Support\Facades\Event;

        class Listener {}

        class TriggersServiceProvider {
            public function boot(array $events): void {
                foreach ($events as $event) {
                    Event::listen($event, Listener::class);
                }
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }
}
