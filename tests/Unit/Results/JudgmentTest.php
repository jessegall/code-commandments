<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Results;

use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Tests\TestCase;

class JudgmentTest extends TestCase
{
    public function test_righteous_judgment(): void
    {
        $judgment = Judgment::righteous();

        $this->assertTrue($judgment->isRighteous());
        $this->assertFalse($judgment->isFallen());
        $this->assertFalse($judgment->hasWarnings());
        $this->assertEquals(0, $judgment->sinCount());
    }

    public function test_fallen_judgment(): void
    {
        $sins = [
            Sin::at(10, 'Test sin'),
            Sin::at(20, 'Another sin'),
        ];

        $judgment = Judgment::fallen($sins);

        $this->assertFalse($judgment->isRighteous());
        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(2, $judgment->sinCount());
    }

    public function test_judgment_with_warnings(): void
    {
        $warnings = [
            Warning::at(5, 'Test warning'),
        ];

        $judgment = Judgment::withWarnings($warnings);

        $this->assertTrue($judgment->isRighteous()); // Warnings don't fail
        $this->assertFalse($judgment->isFallen());
        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_skipped_judgment(): void
    {
        $judgment = Judgment::skipped('Not applicable');

        $this->assertFalse($judgment->isRighteous()); // Skipped is not righteous
        $this->assertFalse($judgment->isFallen());
        $this->assertTrue($judgment->skipped);
        $this->assertEquals('Not applicable', $judgment->skipReason);
    }

    public function test_merge_judgments(): void
    {
        $sins1 = [Sin::at(1, 'Sin 1')];
        $sins2 = [Sin::at(2, 'Sin 2')];

        $judgment1 = Judgment::fallen($sins1);
        $judgment2 = Judgment::fallen($sins2);

        $merged = $judgment1->merge($judgment2);

        $this->assertEquals(2, $merged->sinCount());
        $this->assertTrue($merged->isFallen());
    }

    public function test_merge_with_warnings(): void
    {
        $warnings1 = [Warning::at(1, 'Warning 1')];
        $warnings2 = [Warning::at(2, 'Warning 2')];

        $judgment1 = Judgment::withWarnings($warnings1);
        $judgment2 = Judgment::withWarnings($warnings2);

        $merged = $judgment1->merge($judgment2);

        $this->assertCount(2, $merged->warnings);
    }
}
