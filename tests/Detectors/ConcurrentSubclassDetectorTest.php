<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConcurrentSubclassDetector;
use PHPUnit\Framework\TestCase;

final class ConcurrentSubclassDetectorTest extends TestCase
{
    public function test_flags_a_class_that_extends_concurrent_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        use JesseGall\Concurrent\Concurrent;

        class CartSession extends Concurrent
        {
            public int $count = 0;
        }

        class CheckoutSession
        {
            private int $count = 0;

            public static function for(int $id): Concurrent
            {
                return new Concurrent(key: "checkout:{$id}", default: new self, ttl: 600);
            }
        }
        PHP;

        $hits = (new ConcurrentSubclassDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\CartSession'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
