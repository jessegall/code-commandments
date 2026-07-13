<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\ContainerReachDetector;
use PHPUnit\Framework\TestCase;

final class ContainerReachDetectorTest extends TestCase
{
    public function test_flags_a_literal_class_target_but_not_a_runtime_class_string(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        class Service
        {
            // statically known — provably injectable, so a sin
            public function known(): object
            {
                return app(Mailer::class);
            }

            // runtime class-string — DI can't replace this, not a sin
            public function dynamic(string $pipe): object
            {
                return app($pipe);
            }
        }
        PHP;

        $hits = (new ContainerReachDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Service::known'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_does_not_flag_a_class_resolving_itself_from_a_static_factory(): void
    {
        // app(static::class, $args) in a static make() is the construction seam that GIVES
        // the class constructor DI — self-construction, not a dependency reach (#342, #346).
        $code = <<<'PHP'
        <?php
        namespace App;

        class PaletteItems
        {
            public function __construct(private Registry $registry) {}

            public static function make(mixed ...$args): static
            {
                return app(static::class, $args);
            }

            public static function fromSelf(): self
            {
                return app(self::class);
            }

            public static function byOwnName(): self
            {
                return app(PaletteItems::class);
            }

            // resolving ANOTHER class is still the sin
            public function reach(): object
            {
                return app(Mailer::class);
            }
        }
        PHP;

        $hits = (new ContainerReachDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\PaletteItems::reach'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
