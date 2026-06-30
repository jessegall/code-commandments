<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class DuplicateElementDetectorTest extends TestCase
{
    public function test_flags_identical_blocks_ignoring_formatting(): void
    {
        // Same markup, different indentation/whitespace — still identical by structure.
        $found = $this->find(<<<'VUE'
            <template>
              <form>
                <div class="field"><label>Name</label><input name="name" /></div>
                <div class="field">
                    <label>Name</label>
                    <input name="name" />
                </div>
              </form>
            </template>
            VUE);

        $this->assertCount(2, $found);
    }

    public function test_reports_only_the_outermost_duplicated_block(): void
    {
        // Both <section>s are identical AND so is the inner .field — only the section
        // (the largest duplicated block) is flagged.
        $found = $this->find(<<<'VUE'
            <template>
              <div>
                <section class="s"><div class="field"><label>A</label><input name="a" /></div></section>
                <section class="s"><div class="field"><label>A</label><input name="a" /></div></section>
              </div>
            </template>
            VUE);

        $this->assertCount(2, $found);
        $this->assertSame(['section', 'section'], array_map(static fn ($m): string => $m->tag, $found));
    }

    public function test_ignores_blocks_that_differ(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <div class="a"><label>A</label><input name="a" /></div>
              <div class="b"><label>B</label><input name="b" /></div>
            </template>
            VUE);

        $this->assertCount(0, $found);
    }

    public function test_ignores_trivial_repeats_below_the_floor(): void
    {
        $found = $this->find(<<<'VUE'
            <template>
              <hr />
              <hr />
              <div class="x" />
              <div class="x" />
            </template>
            VUE);

        $this->assertCount(0, $found);
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $vue): array
    {
        return new DuplicateElementDetector()->find(Codebase::fromString($vue));
    }
}
