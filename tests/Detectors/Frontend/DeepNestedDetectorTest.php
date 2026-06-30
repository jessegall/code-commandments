<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class DeepNestedDetectorTest extends TestCase
{
    public function test_flags_a_deeply_nested_subtree(): void
    {
        // A 14-deep chain (well past 8, with 4+ levels still below) → one finding.
        $found = $this->find($this->chain(14));

        $this->assertCount(1, $found);
    }

    public function test_climbs_back_to_the_natural_root(): void
    {
        // The deep chain is the sole content of <section>; the boundary is that
        // section (climb stops where the root branches), not a div mid-chain.
        $found = $this->find($this->chain(14));

        $this->assertSame('section', $found[0]->tag);
    }

    public function test_ignores_shallow_nesting(): void
    {
        $this->assertCount(0, $this->find($this->chain(6)));
    }

    public function test_ignores_deep_but_thin(): void
    {
        // 10 deep, but the deepest element past level 8 has only ~2 levels below it —
        // nothing substantial to extract.
        $this->assertCount(0, $this->find($this->chain(10)));
    }

    /**
     * A `<section>` wrapping `$depth` nested `<div>`s around a leaf, beside a sibling
     * so the section is a single-child wrapper the boundary can climb to.
     */
    private function chain(int $depth): string
    {
        $open = str_repeat('<div>', $depth);
        $close = str_repeat('</div>', $depth);

        return "<template>\n  <section>{$open}<p>{{ entry.field }}</p>{$close}</section>\n  <footer>end</footer>\n</template>";
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $template): array
    {
        return new DeepNestedDetector()->find(Codebase::fromString($template));
    }
}
