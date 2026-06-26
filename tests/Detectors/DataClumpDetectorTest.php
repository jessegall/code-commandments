<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector;
use PHPUnit\Framework\TestCase;

final class DataClumpDetectorTest extends TestCase
{
    public function test_flags_a_value_trio_recurring_across_two_classes(): void
    {
        $code = <<<'PHP'
        <?php
        class Feed {
            public function publish(string $shopId, string $userId, string $channelId): void {}
        }
        class Sync {
            public function reconcile(string $shopId, string $userId, string $channelId): void {}
        }
        class Lonely {
            public function once(string $a, string $b, string $c): void {}
        }
        PHP;

        $hits = (new DataClumpDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['Feed::publish', 'Sync::reconcile'], $scopes);
    }

    public function test_a_constructor_taking_the_fields_is_not_a_clump(): void
    {
        $code = <<<'PHP'
        <?php
        class A { public function __construct(string $shopId, string $userId, string $channelId) {} }
        class B { public function __construct(string $shopId, string $userId, string $channelId) {} }
        PHP;

        $this->assertSame([], (new DataClumpDetector)->find(Codebase::fromString($code)));
    }
}
