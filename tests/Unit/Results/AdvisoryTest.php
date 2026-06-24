<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Results;

use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Tests\TestCase;

class AdvisoryTest extends TestCase
{
    public function test_fluent_builder_sets_all_three_facets(): void
    {
        $advisory = Advisory::make()
            ->applyWhen('many callers')
            ->leaveWhen('one caller')
            ->whenUnsure('leave it');

        $this->assertSame('many callers', $advisory->applyWhen);
        $this->assertSame('one caller', $advisory->leaveWhen);
        $this->assertSame('leave it', $advisory->whenUnsure);
        $this->assertTrue($advisory->isComplete());
    }

    public function test_incomplete_advisory_is_flagged(): void
    {
        $this->assertFalse(Advisory::make()->applyWhen('x')->isComplete());
    }

    public function test_lines_are_labelled(): void
    {
        $lines = Advisory::make()
            ->applyWhen('A')
            ->leaveWhen('B')
            ->whenUnsure('C')
            ->lines();

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('APPLY WHEN:', $lines[0]);
        $this->assertStringContainsString('LEAVE WHEN:', $lines[1]);
        $this->assertStringContainsString('IF UNSURE:', $lines[2]);
    }

    public function test_advisory_prophet_exposes_complete_rubric(): void
    {
        $advisory = (new OptionDisciplineProphet())->advisory();

        $this->assertInstanceOf(Advisory::class, $advisory);
        $this->assertTrue($advisory->isComplete());
    }

    public function test_imperative_prophet_has_no_advisory(): void
    {
        $this->assertNull((new NoRawRequestProphet())->advisory());
    }
}
