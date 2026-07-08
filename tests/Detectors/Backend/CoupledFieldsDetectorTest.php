<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\CoupledFieldsDetector;
use PHPUnit\Framework\TestCase;

/**
 * The detector is {@see \JesseGall\CodeCommandments\Unpublished} (not in the catalog yet), so it is proven
 * here by direct instantiation while it is calibrated. A clump is made of VALUES — a group of injected
 * SERVICES forwarded together must NOT flag.
 */
final class CoupledFieldsDetectorTest extends TestCase
{
    // ── 1. coupled values ────────────────────────────────────────────────────

    public function test_flags_the_same_value_pair_assembled_in_two_places(): void
    {
        $this->assertFlagged(<<<'PHP'
        final class Line {
            public function __construct(
                public readonly int $startX,
                public readonly int $startY,
                public readonly string $label,
            ) {}
            public function origin(): array { return [$this->startX, $this->startY]; }
            public function shifted(): array { return [$this->startX, $this->startY]; }
        }
        PHP);
    }

    public function test_flags_value_siblings_guarded_and_assembled_together(): void
    {
        $this->assertFlagged(<<<'PHP'
        final class Range {
            public function __construct(
                public readonly ?int $lo = null,
                public readonly ?int $hi = null,
                public readonly string $unit = '',
            ) {}
            public function pair(): array {
                return $this->lo !== null && $this->hi !== null ? [$this->lo, $this->hi] : [];
            }
        }
        PHP);
    }

    public function test_does_not_flag_two_services_forwarded_together(): void
    {
        // Repo/Db are not values (unresolved services) — forwarding them into a sub-object is not a clump.
        $this->assertNotFlagged(<<<'PHP'
        final class Pipeline {
            public function __construct(
                private readonly Repo $repo,
                private readonly Db $db,
                private array $steps = [],
            ) {}
            public function a(): Step { return new Step($this->repo, $this->db); }
            public function b(): Step { return new Step($this->repo, $this->db); }
        }
        PHP);
    }

    public function test_does_not_flag_a_full_1to1_mapping_of_all_fields(): void
    {
        $this->assertNotFlagged(<<<'PHP'
        final class Point {
            public function __construct(public readonly int $x, public readonly int $y) {}
            public function toArray(): array { return [$this->x, $this->y]; }
            public function copy(): array { return [$this->x, $this->y]; }
        }
        PHP);
    }

    // ── 2. cross-object clump ────────────────────────────────────────────────

    public function test_flags_a_value_field_paired_with_a_reach_into_a_value_object(): void
    {
        $this->assertFlagged(<<<'PHP'
        final class Node { public function __construct(public readonly string $id) {} }
        final class Edge { public function __construct(public readonly Node $toNode) {} }
        final class Connection {
            public function __construct(public readonly Node $fromNode, public readonly Edge $edge) {}
            public function endpoints(): array { return [$this->fromNode, $this->edge->toNode]; }
            public function pair(): array { return [$this->fromNode, $this->edge->toNode]; }
        }
        PHP, 'Connection');
    }

    public function test_does_not_flag_an_aggregate_reaching_a_different_type(): void
    {
        // A turn HOLDS a graph and a node and reaches node.id — different types, a hierarchy/context, not
        // two peers of one concept. Must NOT flag (the AgentTurn/NodeDescriptor shape).
        $this->assertNotFlagged(<<<'PHP'
        final class Node { public function __construct(public readonly string $id) {} }
        final class Graph { public function __construct(public readonly string $name) {} }
        final class Turn {
            public function __construct(public readonly Graph $graph, public readonly Node $node) {}
            public function a(): array { return [$this->graph, $this->node->id]; }
            public function b(): array { return [$this->graph, $this->node->id]; }
        }
        PHP);
    }

    // ── 3. redundant mirror ──────────────────────────────────────────────────

    public function test_flags_a_field_whose_name_encodes_a_nested_objects_property(): void
    {
        $this->assertFlagged(<<<'PHP'
        final class Product {
            public function __construct(public readonly string $name, public readonly int $price) {}
        }
        final class OrderLine {
            public function __construct(
                public readonly Product $product,
                public readonly string $productName,
                public readonly int $productPrice,
            ) {}
        }
        PHP, 'OrderLine');
    }

    public function test_does_not_flag_a_bare_same_named_field_on_an_unrelated_object(): void
    {
        // advisory.id and fix.id are both `id` but unrelated — a bare name match is coincidental, not encoded.
        $this->assertNotFlagged(<<<'PHP'
        final class Fix { public function __construct(public readonly string $id) {} }
        final class Advisory {
            public function __construct(
                public readonly string $id,
                public readonly Fix $fix,
                public readonly string $title,
            ) {}
        }
        PHP);
    }

    public function test_does_not_flag_a_reassigned_field_that_looks_like_a_mirror(): void
    {
        $this->assertNotFlagged(<<<'PHP'
        final class Product { public function __construct(public readonly string $name) {} }
        final class Cache {
            public string $productName;
            public function __construct(public readonly Product $product) { $this->productName = ''; }
            public function refresh(): void { $this->productName = $this->product->name; }
        }
        PHP);
    }

    // ── righteous ────────────────────────────────────────────────────────────

    public function test_does_not_flag_independently_used_fields(): void
    {
        $this->assertNotFlagged(<<<'PHP'
        final class Router {
            public function __construct(private readonly int $from, private readonly int $to) {}
            public function low(): int { return $this->from; }
            public function high(): int { return $this->to; }
        }
        PHP);
    }

    private function assertFlagged(string $php, string $className = ''): void
    {
        $names = $this->flaggedClasses($php);
        $this->assertNotEmpty($names, 'expected a coupled-fields finding');

        if ($className !== '') {
            $this->assertContains($className, $names);
        }
    }

    private function assertNotFlagged(string $php): void
    {
        $this->assertSame([], $this->flaggedClasses($php));
    }

    /** @return list<string> */
    private function flaggedClasses(string $php): array
    {
        $codebase = Codebase::fromString("<?php\nnamespace App;\n" . $php, '/proj/app/File.php');

        return array_values(array_map(
            static function ($m): string {
                $class = explode('::', (string) $m->scope())[0];
                $parts = explode('\\', $class);

                return end($parts);
            },
            new CoupledFieldsDetector()->find($codebase),
        ));
    }
}
