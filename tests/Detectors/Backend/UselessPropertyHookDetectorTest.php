<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\UselessPropertyHookDetector;
use PHPUnit\Framework\TestCase;

final class UselessPropertyHookDetectorTest extends TestCase
{
    public function test_flags_a_get_hook_that_reads_no_instance_state(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        class Transition { public static function make(mixed ...$a): self { return new self(); } }

        class World
        {
            // a constant: this IS a plain property with a default
            public ?Transition $selfTransition { get => null; }

            // same construction every read, nothing from $this — assign once instead
            public ?Transition $listTransition {
                get => Transition::make('fade', 'morph');
            }

            // genuinely derived — reads $this, the hook is earned
            public string $label { get => strtoupper($this->name); }

            public function __construct(public string $name) {}
        }
        PHP;

        $hits = (new UselessPropertyHookDetector)->find(Codebase::fromString($code));

        $this->assertCount(2, $hits);
        $this->assertSame([9, 13], array_map(static fn ($m): int => $m->line(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        // the interface's abstract hook declaration has no body — nothing to judge
        interface Animated
        {
            public ?string $transition { get; }
        }

        class Card implements Animated
        {
            // derived from own state through a method call
            public ?string $transition { get => $this->resolveTransition(); }

            // a block-bodied hook that branches on $this
            public string $badge {
                get {
                    return $this->count > 0 ? 'busy' : 'idle';
                }
            }

            private int $count = 0;

            private function resolveTransition(): ?string
            {
                return null;
            }
        }

        // a set hook alongside means the property earns hook syntax as a pair
        class Guarded
        {
            public string $email {
                get => 'redacted';
                set => strtolower($value);
            }
        }
        PHP;

        $this->assertSame([], (new UselessPropertyHookDetector)->find(Codebase::fromString($code)));
    }

    public function test_does_not_flag_a_hook_calling_own_static_behaviour(): void
    {
        // `self::`/`static::` CALLS are the class's own (late-bound) behaviour — the value
        // genuinely derives from which class you are, exactly like `$this->…`. A constant
        // read off ANOTHER type stays a plain default, so it stays flagged.
        $code = <<<'PHP'
        <?php
        namespace App;

        enum Severity: string { case Error = 'error'; }

        trait BelongsToShop
        {
            public ?string $resourceType { get => self::resolveResourceType(); }

            public static function resolveResourceType(): ?string
            {
                return static::class;
            }
        }

        class Finding
        {
            // an enum case of another type IS a valid property default — still the sin
            public Severity $severity { get => Severity::Error; }

            // own static property — late-bound state, earned
            public int $sequence { get => static::$counter; }

            public static int $counter = 0;
        }
        PHP;

        $hits = (new UselessPropertyHookDetector)->find(Codebase::fromString($code));

        $this->assertCount(1, $hits);
        $this->assertSame('App\\Finding', $hits[0]->scope());
    }

    public function test_does_not_flag_a_hook_reading_a_constant_through_the_late_bound_class(): void
    {
        // `static::DRIVER` resolves per SUBCLASS, so the value is not known where the property is
        // declared and no stored property can express it — the hook is how a base asks which class
        // it is in (#516). `self::` is a constant of the declaring class, so that stays flagged.
        $code = <<<'PHP'
        <?php
        namespace App;

        interface WizardDriver {}

        abstract class Wizard
        {
            protected const string DRIVER = WizardDriver::class;

            public WizardDriver $driver { get => new (static::DRIVER)(); }

            public string $label { get => static::LABEL; }

            protected const string LABEL = 'wizard';
        }

        class Fixed
        {
            private const string DRIVER = 'one';

            public string $driver { get => self::DRIVER; }
        }
        PHP;

        $hits = (new UselessPropertyHookDetector)->find(Codebase::fromString($code));

        $this->assertCount(1, $hits);
        $this->assertSame('App\\Fixed', $hits[0]->scope());
    }

    public function test_does_not_flag_hooks_in_a_trait_consumed_by_a_data_class(): void
    {
        // Page-object concerns live in traits; the trait inherits its consumers' Data
        // exclusion. A trait used only by plain classes is judged normally.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;

            trait HasScannerSimulator
            {
                public bool $scannerSimulator { get => app()->environment('local'); }
            }

            trait HasFixedBadge
            {
                public string $badge { get => 'static-badge'; }
            }

            class DashboardPage extends Data
            {
                use HasScannerSimulator;
            }

            class PlainWidget
            {
                use HasFixedBadge;
            }
        }
        PHP;

        $hits = (new UselessPropertyHookDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\HasFixedBadge'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_hooks_inside_a_spatie_data_class_alone(): void
    {
        // Data classes legitimately use hooks for computed/serialized fields (page
        // objects and friends) — the whole class is out of scope for this sin.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;

            class StatusPage extends Data
            {
                public string $version { get => '1.0.0'; }
            }

            class Plain
            {
                public string $version { get => '1.0.0'; }
            }
        }
        PHP;

        $hits = (new UselessPropertyHookDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Plain'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
