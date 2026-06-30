<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NullableRegistryLookupDetector;
use PHPUnit\Framework\TestCase;

final class NullableRegistryLookupDetectorTest extends TestCase
{
    public function test_flags_an_owned_keyed_store_returning_null_on_miss_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Channels
        {
            private array $channels = [];

            public function get(string $key): ?object
            {
                return $this->channels[$key] ?? null;
            }
        }

        class Attributes
        {
            public function value(array $attributes, string $key): mixed
            {
                return $attributes[$key] ?? null;
            }
        }

        class Gateways
        {
            private array $items = [];

            public function get(string $key): object
            {
                return $this->items[$key] ?? throw new \RuntimeException($key);
            }
        }
        PHP;

        $hits = (new NullableRegistryLookupDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Channels::get'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
