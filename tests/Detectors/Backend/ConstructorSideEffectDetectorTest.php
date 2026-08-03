<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConstructorSideEffectDetector;
use PHPUnit\Framework\TestCase;

final class ConstructorSideEffectDetectorTest extends TestCase
{
    public function test_flags_a_constructor_that_acts_on_a_collaborator_and_keeps_nothing(): void
    {
        $code = <<<'PHP'
        <?php
        class Session {
            private string $id = '';

            public function loadMissing(string $relation): void {}
        }
        class Globals {
            private array $values = [];

            public function default(string $key, string $value): void {}
        }
        class Crawler {
            public function __construct(private Session $session) {
                $this->session->loadMissing('supplier');
            }
        }
        class SkeletonsSetting {
            public function __construct(Globals $globals) {
                $globals->default('skeletons', 'on');
            }
        }
        PHP;

        $hits = (new ConstructorSideEffectDetector)->find(Codebase::fromString($code));

        $this->assertSame(
            ['Crawler', 'SkeletonsSetting'],
            array_map(static fn ($m): string => $m->scope(), $hits),
        );
    }

    public function test_leaves_every_shape_of_assembly_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class HttpClient {
            private string $base = '';

            public function get(string $path): array { return []; }
        }
        class Kind {
            public function __construct(private string $name) {}

            public function label(): string { return $this->name; }
        }
        class Asked {
            private array $skus;

            public function __construct(HttpClient $client) {
                $this->skus = $client->get('/skus');
            }
        }
        class Derived {
            private string $slug;

            public function __construct(private string $title) {
                $this->slug = strtolower($title);
            }
        }
        class Guarded {
            public function __construct(private string $sku) {
                if ($sku === '') {
                    throw new \InvalidArgumentException('sku is required');
                }
            }
        }
        class FromAValue {
            private string $label;

            public function __construct(Kind $kind) {
                $this->label = $kind->label();
            }
        }
        class Assembles {
            private string $name;

            public function __construct(string $raw) {
                $this->name = $this->normalise($raw);
            }

            private function normalise(string $raw): string { return trim($raw); }
        }
        PHP;

        $hits = (new ConstructorSideEffectDetector)->find(Codebase::fromString($code));

        // Asking a collaborator FOR something and building yourself out of it is construction and
        // cannot be told from I/O. Deriving, guarding, reading a value and calling your own helper
        // are all assembly. Only acting on a collaborator and keeping nothing is a side effect.
        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
