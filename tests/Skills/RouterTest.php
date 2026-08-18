<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Skills\Briefing;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Router;
use PHPUnit\Framework\TestCase;

/**
 * The canon, published as a skill an agent can load. Asking an agent to load fourteen disciplines
 * before it writes a line is a request it quietly declines; one map it can reach for, plus a
 * description on every discipline saying when that one fires, is a request it can actually honour.
 */
final class RouterTest extends TestCase
{
    public function test_it_publishes_as_a_loadable_skill(): void
    {
        $rendered = Router::render();

        $this->assertStringStartsWith("---\nname: commandments\ndescription: \"", $rendered);
        $this->assertStringContainsString("\n---\n", $rendered);
        $this->assertStringContainsString('# Code Commandments', $rendered);
    }

    public function test_it_is_the_briefing_itself_rather_than_a_second_account_of_it(): void
    {
        $this->assertStringContainsString(Briefing::render(), Router::render());
    }

    public function test_it_names_every_discipline_and_where_each_one_sits(): void
    {
        $rendered = Router::render();

        $this->assertStringContainsString('CONSTANTLY IN PLAY', $rendered);
        $this->assertStringContainsString('ON CONTACT', $rendered);

        foreach (Skills::all() as $skill) {
            $this->assertStringContainsString("`{$skill->id()}`", $rendered, "{$skill->id()} missing from the map");
        }
    }

    public function test_its_description_names_the_moments_a_map_is_what_you_need(): void
    {
        $description = explode("\n", Router::render())[2];

        // The one skill whose trigger is not a syntax you are about to write. If it read like a
        // discipline's trigger it would compete with all of them and win none.
        $this->assertStringContainsString('WHICH discipline', $description);
        $this->assertStringContainsString('compaction', $description);
    }

    public function test_the_briefing_no_longer_demands_a_bulk_load_before_any_work(): void
    {
        $block = Briefing::render();

        $this->assertStringNotContainsString('Do not start work without all of them loaded', $block);
        $this->assertStringContainsString('`commandments-backend-fix-at-the-source`', $block);
    }
}
