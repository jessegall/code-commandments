<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Cli\Judge\Finding;
use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Skills\Skill;
use PHPUnit\Framework\TestCase;

/**
 * The report doesn't say "read a file" — a passive line an agent skims past. It names the exact Skill-tool
 * id to LOAD, and warns that a skill believed-loaded may have been dropped by a compaction, so it must be
 * loaded again rather than fixed from memory. Both surfaces (console + checklist) carry the imperative.
 */
final class SkillLoadInstructionTest extends TestCase
{
    private function report(): SinReport
    {
        return new SinReport('/app', [
            new Finding('ArrayBagDetector', 'backend/value-objects', 'array-bag', '/app/Cart.php', '/app/Cart.php:12', 'Cart::totals'),
        ]);
    }

    public function test_console_names_the_skill_tool_id_to_load(): void
    {
        $console = $this->report()->console();

        // The exact invocation name the Skill tool loads — reused from Skill, never re-spelled here.
        $this->assertSame('commandments-backend-value-objects', Skill::idFor('backend/value-objects'));
        $this->assertStringContainsString('commandments-backend-value-objects', $console);
        $this->assertStringContainsString('Skill tool', $console);
        $this->assertStringContainsString('LOAD this skill', $console);
    }

    public function test_console_warns_a_believed_loaded_skill_may_be_gone(): void
    {
        $console = $this->report()->console();

        $this->assertStringContainsString('compaction', strtolower($console));
        $this->assertStringContainsString('load it again', strtolower($console));
    }

    public function test_checklist_names_the_skill_tool_id_and_the_reload_warning(): void
    {
        $checklist = $this->report()->checklist();

        $this->assertStringContainsString('`commandments-backend-value-objects`', $checklist);
        $this->assertStringContainsString('invoke the Skill tool', $checklist);
        // The header step and the section header both drop "read the file" for "load it, don't trust memory".
        $this->assertStringContainsString('LOAD the skill', $checklist);
        $this->assertStringContainsString('compaction', strtolower($checklist));
        $this->assertStringNotContainsString('SKILL.md', $checklist, 'no passive "read this file" path survives');
    }
}
