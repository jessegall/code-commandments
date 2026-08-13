<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Briefing;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use PHPUnit\Framework\TestCase;

/**
 * The briefing describes THIS codebase. A project with no `.vue` files was still told to load the
 * Vue skills as mandatory reading, and the rules they teach can never fire there (#478) — a claim
 * about a codebase that is not the reader's, inside a managed block they cannot correct by hand.
 */
final class BriefingHonoursLanguagesTest extends TestCase
{
    public function test_a_project_that_writes_no_vue_is_never_told_to_load_a_vue_skill(): void
    {
        $block = Briefing::render(null, new Languages(Language::Vue));

        $this->assertStringNotContainsString('commandments-frontend-vue-components', $block);
        $this->assertStringNotContainsString('commandments-frontend-vue-control-flow', $block);
    }

    public function test_the_typescript_disciplines_survive_a_project_with_no_vue(): void
    {
        // A TypeScript-only frontend is still a frontend: the language's own rules, and the
        // mirrored-server-type discipline whose duplicated contract is declared in TypeScript.
        $block = Briefing::render(null, new Languages(Language::Vue));

        $this->assertStringContainsString('commandments-typescript-absence', $block);
        $this->assertStringContainsString('commandments-frontend-mirrored-server-type', $block);
        $this->assertStringContainsString('commandments-backend-absence', $block);
    }

    public function test_a_php_only_project_hears_about_php_alone(): void
    {
        $block = Briefing::render(null, new Languages(Language::Vue, Language::TypeScript));

        $this->assertStringNotContainsString('commandments-typescript-absence', $block);
        $this->assertStringNotContainsString('commandments-frontend-vue-components', $block);
        $this->assertStringNotContainsString('commandments-frontend-mirrored-server-type', $block);
        $this->assertStringContainsString('commandments-backend-fix-at-the-source', $block);
    }

    public function test_a_project_that_has_said_nothing_is_briefed_on_everything(): void
    {
        $block = Briefing::render(null, new Languages());

        foreach (Skills::all() as $skill) {
            $this->assertStringContainsString("`{$skill->id()}`", $block, "{$skill->id()} missing from the briefing");
        }
    }

    public function test_the_frontend_guidance_names_the_engine_rather_than_one_framework(): void
    {
        $block = Briefing::render(null, new Languages());

        $this->assertStringContainsString('Vue components and plain TypeScript', $block);
        $this->assertStringNotContainsString('app/Http', $block, 'an example path no package or non-Laravel project has');
    }
}
