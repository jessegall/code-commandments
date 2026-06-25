<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Support\Skills\SkillDigest;
use JesseGall\CodeCommandments\Support\Skills\SkillRegistry;
use PHPUnit\Framework\TestCase;

class SkillDigestTest extends TestCase
{
    public function test_digest_lists_every_skill_with_a_trigger(): void
    {
        $digest = SkillDigest::render();

        $this->assertStringContainsString('CODE COMMANDMENTS SKILLS', $digest);
        $this->assertStringContainsString('.claude/skills/commandments-', $digest);

        foreach (SkillRegistry::all() as $skill) {
            if ($skill->autoload) {
                $this->assertStringContainsString('- ' . $skill->slug . ' — ', $digest, "digest missing {$skill->slug}");
            } else {
                // Command-triggered skills (e.g. handoff) are installed but not
                // force-surfaced in the session-start digest.
                $this->assertStringNotContainsString('- ' . $skill->slug . ' — ', $digest, "digest should omit non-autoload {$skill->slug}");
            }
        }
    }

    public function test_digest_is_compact_one_line_per_skill(): void
    {
        // Each skill is a single line capped to a trigger clause — so the whole
        // index stays cheap to inject at session start (header + blank + N lines).
        $digest = SkillDigest::render();
        $bodyLines = array_values(array_filter(
            explode("\n", $digest),
            static fn (string $l): bool => str_starts_with($l, '- '),
        ));

        $autoloaded = array_filter(SkillRegistry::all(), static fn ($s): bool => $s->autoload);
        $this->assertCount(count($autoloaded), $bodyLines);

        foreach ($bodyLines as $line) {
            $this->assertLessThanOrEqual(230, mb_strlen($line), "digest line too long: {$line}");
        }
    }
}
