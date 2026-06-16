<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\FindingQueue;
use JesseGall\CodeCommandments\Tests\TestCase;

class FindingQueueTest extends TestCase
{
    private function finding(
        string $prophet,
        string $file,
        ?int $line,
        Tier $tier = Tier::Convention,
        array $supersedes = [],
    ): Finding {
        return new Finding(
            prophetClass: $prophet,
            prophetShort: $prophet,
            filePath: '/abs/' . $file,
            relativePath: $file,
            kind: 'sin',
            line: $line,
            message: $prophet . ' at ' . $file . ':' . $line,
            snippet: null,
            suggestion: null,
            symbol: null,
            advisory: null,
            tier: $tier,
            supersedes: $supersedes,
            fingerprint: md5($prophet . $file . $line),
        );
    }

    public function test_orders_by_tier_then_file_then_line(): void
    {
        $cosmetic = $this->finding('Cosmetic', 'a.php', 10, Tier::Cosmetic);
        $structuralLate = $this->finding('Struct', 'b.php', 5, Tier::Structural);
        $structuralEarly = $this->finding('Struct', 'a.php', 99, Tier::Structural);

        $ordered = FindingQueue::order([$cosmetic, $structuralLate, $structuralEarly]);

        // Structural first, and within structural, file a.php before b.php.
        $this->assertSame($structuralEarly, $ordered[0]);
        $this->assertSame($structuralLate, $ordered[1]);
        $this->assertSame($cosmetic, $ordered[2]);
    }

    public function test_defers_superseded_finding_in_same_region(): void
    {
        $bag = $this->finding('Bag', 'a.php', 10, Tier::Structural, ['Indexing']);
        $indexing = $this->finding('Indexing', 'a.php', 12);

        $ordered = FindingQueue::order([$bag, $indexing]);

        $this->assertCount(1, $ordered);
        $this->assertSame($bag, $ordered[0]);
    }

    public function test_does_not_defer_superseded_finding_far_away(): void
    {
        $bag = $this->finding('Bag', 'a.php', 10, Tier::Structural, ['Indexing']);
        $indexingFar = $this->finding('Indexing', 'a.php', 500);

        $ordered = FindingQueue::order([$bag, $indexingFar]);

        $this->assertCount(2, $ordered);
    }

    public function test_does_not_defer_superseded_finding_in_other_file(): void
    {
        $bag = $this->finding('Bag', 'a.php', 10, Tier::Structural, ['Indexing']);
        $indexingOther = $this->finding('Indexing', 'b.php', 10);

        $ordered = FindingQueue::order([$bag, $indexingOther]);

        $this->assertCount(2, $ordered);
    }
}
