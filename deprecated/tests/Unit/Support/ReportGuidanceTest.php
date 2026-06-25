<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ReportGuidance;
use JesseGall\CodeCommandments\Tests\TestCase;

class ReportGuidanceTest extends TestCase
{
    public function test_no_guidance_without_an_issue_number(): void
    {
        $this->assertSame([], ReportGuidance::lines(null, 'owner/repo'));
    }

    public function test_guidance_watches_the_issue_and_branches_on_resolution(): void
    {
        $text = implode("\n", ReportGuidance::lines(42, 'jessegall/code-commandments'));

        // Watch: both the durable reports --check path and an active poll loop
        // (a concrete, paste-able /loop invocation).
        $this->assertStringContainsString('reports --check', $text);
        $this->assertStringContainsString('/loop 5m', $text);
        $this->assertStringContainsString('gh issue view 42 --repo jessegall/code-commandments', $text);

        // Branch on resolution type.
        $this->assertStringContainsString('composer update', $text);
        $this->assertStringContainsString('FIX THE CODE', $text);
    }
}
