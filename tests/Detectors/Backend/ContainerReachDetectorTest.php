<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ContainerReachDetector;
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
}
