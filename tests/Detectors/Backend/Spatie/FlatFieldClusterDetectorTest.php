<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\FlatFieldClusterDetector;
use PHPUnit\Framework\TestCase;

/**
 * The flat-field-cluster detector: it fires only when a `#[TypeScript]` Data class flattens a value object
 * the codebase ALREADY models — a small VO named for the prefix whose fields ARE the flat cluster's
 * remainders — and stays quiet on references, boolean flags, function-word prefixes, and shape mismatches.
 */
final class FlatFieldClusterDetectorTest extends TestCase
{
    private const string WIRE_VO = 'class Wire extends Data { public function __construct(public string $type, public string $socket, public string $label) {} }';

    public function test_flags_flattening_an_existing_value_object(): void
    {
        // `wireType`/`wireLabel` restate the fields of the `Wire` value object the codebase already has.
        $this->assertSame(['App\\Port'], $this->hits(self::WIRE_VO . <<<'PHP'
        #[TypeScript]
        class Port extends Data {
            public function __construct(public string $wireType, public string $wireLabel, public int $order) {}
        }
        PHP));
    }

    public function test_ignores_a_cluster_with_no_matching_value_object(): void
    {
        // No `Broadcast` VO exists — the flat fields don't restate anything modelled, so leave them alone.
        $this->assertSame([], $this->hits(<<<'PHP'
        #[TypeScript]
        class Port extends Data {
            public function __construct(public string $broadcastUrl, public bool $broadcastEnabled) {}
        }
        PHP));
    }

    public function test_ignores_a_reference_cluster_even_with_a_matching_vo(): void
    {
        // A `…Id` member makes it a foreign-key reference (+ denormalized label), which is honestly flat.
        $this->assertSame([], $this->hits(self::WIRE_VO . <<<'PHP'
        #[TypeScript]
        class Port extends Data {
            public function __construct(public string $wireId, public string $wireLabel) {}
        }
        PHP));
    }

    public function test_ignores_a_shape_mismatch(): void
    {
        // `wireColor`/`wireGauge` — the remainders are NOT fields of `Wire`, so it isn't flattening it.
        $this->assertSame([], $this->hits(self::WIRE_VO . <<<'PHP'
        #[TypeScript]
        class Port extends Data {
            public function __construct(public string $wireColor, public string $wireGauge) {}
        }
        PHP));
    }

    public function test_ignores_a_data_without_typescript(): void
    {
        $this->assertSame([], $this->hits(self::WIRE_VO . <<<'PHP'
        class Port extends Data {
            public function __construct(public string $wireType, public string $wireLabel) {}
        }
        PHP));
    }

    public function test_ignores_a_wide_entity_named_like_the_prefix(): void
    {
        // A class named `Wire` with MANY fields is an entity, not a value object — not a nesting target.
        $wideWire = 'class Wire extends Data { public function __construct(public string $type, public string $label, public string $a, public string $b, public string $c, public string $d, public string $e) {} }';

        $this->assertSame([], $this->hits($wideWire . <<<'PHP'
        #[TypeScript]
        class Port extends Data {
            public function __construct(public string $wireType, public string $wireLabel) {}
        }
        PHP));
    }

    /**
     * @return list<string>
     */
    private function hits(string $body): array
    {
        $code = "<?php namespace App;\nuse Spatie\\LaravelData\\Data;\nuse Spatie\\TypeScriptTransformer\\Attributes\\TypeScript;\n" . $body;

        return array_map(
            static fn ($m): string => (string) $m->scope(),
            (new FlatFieldClusterDetector)->find(Codebase::fromString($code, '/proj/app/X.php')),
        );
    }
}
