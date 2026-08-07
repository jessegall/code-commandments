<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Briefing;
use JesseGall\CodeCommandments\Skills\Tier;
use PHPUnit\Framework\TestCase;

final class BriefingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-briefing-' . uniqid('', true);
        mkdir($this->root . '/.commandments/custom', 0777, true);
        Custom::forget();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        Custom::forget();
    }

    public function test_renders_a_marked_block_listing_every_skill_in_its_tier(): void
    {
        $block = Briefing::render();

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

    public function test_a_project_skill_is_briefed_beside_the_shipped_ones(): void
    {
        // Sync published the SKILL.md but the briefing was built from the shipped catalog alone, so an
        // agent was never told the project's own skill existed (#443).
        $this->writeCustomSkill('ClientDecidesSkill', 'frontend/client-decides');

        $block = Briefing::render($this->root);

        $this->assertStringContainsString('`commandments-frontend-client-decides`', $block);
        $this->assertStringContainsString("_(this project's own", $block, 'and it is named as the project\'s');
    }

    public function test_a_shipped_skill_is_never_marked_as_the_projects_own(): void
    {
        $block = Briefing::render($this->root);

        $this->assertStringContainsString('`commandments-backend-fix-at-the-source`', $block);
        $this->assertStringNotContainsString("_(this project's own", $block);
    }

    /**
     * A project's own skill, written where `commandments make` puts one — discovered BY FILE.
     */
    private function writeCustomSkill(string $class, string $slug): void
    {
        file_put_contents($this->root . "/.commandments/custom/{$class}.php", <<<PHP
        <?php

        namespace Commandments;

        use JesseGall\CodeCommandments\Skills\Skill;
        use JesseGall\CodeCommandments\Skills\Tier;

        final class {$class} extends Skill
        {
            public function __construct()
            {
                parent::__construct(slug: '{$slug}', tier: Tier::KeepInMind, order: 100);
            }

            public function title(): string
            {
                return 'The backend decides, the client performs';
            }

            public function trigger(): string
            {
                return 'Read this before a component decides anything.';
            }

            public function intro(): string
            {
                return 'The client performs what it is told.';
            }

            public function summary(): string
            {
                return 'the client performs, it does not decide';
            }

            public function principle(): string
            {
                return 'A decision taken in the client is a decision the server cannot honour.';
            }
        }
        PHP);
    }
}
