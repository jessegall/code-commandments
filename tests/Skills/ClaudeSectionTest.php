<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\ClaudeSection;
use JesseGall\CodeCommandments\Skills\Tier;
use PHPUnit\Framework\TestCase;

final class ClaudeSectionTest extends TestCase
{
    public function test_renders_a_marked_block_listing_every_skill_in_its_tier(): void
    {
        $block = ClaudeSection::render();

        $this->assertStringStartsWith(ClaudeSection::BEGIN, $block);
        $this->assertStringEndsWith(ClaudeSection::END, $block);
        $this->assertStringContainsString('MANDATORY LOAD', $block);
        $this->assertStringContainsString('KEEP IN MIND', $block);

        foreach (Skills::all() as $skill) {
            $this->assertStringContainsString("`{$skill->id()}`", $block, "{$skill->id()} missing from the briefing");
        }
    }

    public function test_skills_split_into_two_tiers(): void
    {
        $this->assertNotEmpty(Skills::inTier(Tier::Mandatory));
        $this->assertNotEmpty(Skills::inTier(Tier::KeepInMind));
        $this->assertCount(count(Skills::all()), [...Skills::inTier(Tier::Mandatory), ...Skills::inTier(Tier::KeepInMind)]);
    }
}
