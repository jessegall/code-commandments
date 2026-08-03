<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MutableValueObjectDetector;
use PHPUnit\Framework\TestCase;

final class MutableValueObjectDetectorTest extends TestCase
{
    public function test_flags_a_value_that_writes_its_own_field_after_construction(): void
    {
        $code = <<<'PHP'
        <?php
        class Money {
            public function __construct(private int $cents, private string $currency) {}

            public function add(Money $other): void {
                $this->cents += $other->cents;
            }
        }
        class Tally {
            public function __construct(private int $count) {}

            public function bump(): void {
                $this->count = $this->count + 1;
            }
        }
        class Rate {
            public function __construct(private float $factor) {}

            public function times(Rate $other): self {
                return new self($this->factor * $other->factor);
            }
        }
        PHP;

        $hits = (new MutableValueObjectDetector)->find(Codebase::fromString($code));

        // `Rate` derives a new instance instead of changing itself.
        $this->assertSame(['Money', 'Tally'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_a_service_a_lazy_field_and_a_constructor_write_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class HttpClient {
            public function send(string $url): string { return ''; }
        }
        class ApiGateway {
            private int $sent = 0;

            public function __construct(private HttpClient $client) {}

            public function call(string $url): string {
                $this->sent++;

                return $this->client->send($url);
            }
        }
        class Sku {
            private ?string $normalised = null;

            public function __construct(private string $raw) {}

            public function normalised(): string {
                $this->normalised ??= strtoupper($this->raw);

                return $this->normalised;
            }
        }
        class Email {
            private string $domain;

            public function __construct(private string $address) {
                $this->domain = 'example.test';
            }
        }
        PHP;

        $hits = (new MutableValueObjectDetector)->find(Codebase::fromString($code));

        // A service holds a service and is free to change. A `??=` fills a blank that was always
        // going to be filled that way. A constructor write is construction.
        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
