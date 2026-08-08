<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detectors\Frontend\MirroredServerTypeDetector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\LanguageSections;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Testing\Comparison;
use JesseGall\CodeCommandments\Testing\Example;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * A project says which languages it writes, and nothing it does not write is read or taught — the
 * same verb that silences a rule silences a whole language.
 */
final class DisabledLanguageTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/Fixtures/frontend';

    public function test_the_same_disable_verb_takes_a_language(): void
    {
        $config = new Config()->disable(Language::TypeScript);

        $this->assertFalse($config->writes(Language::TypeScript));
        $this->assertTrue($config->writes(Language::Vue));
        $this->assertSame([Language::TypeScript], $config->disabledLanguages());
    }

    /**
     * The claim that makes it real: a disabled language's files are never PARSED, so no rule can
     * report a finding in one.
     */
    public function test_a_disabled_language_is_never_scanned(): void
    {
        $all = Codebase::scan(self::FIXTURE);
        $withoutTypeScript = Codebase::scan(self::FIXTURE, languages: new Languages(Language::TypeScript));

        $this->assertNotSame([], $this->standaloneModules($all), 'the fixture has .ts modules to begin with');
        $this->assertSame([], $this->standaloneModules($withoutTypeScript));

        // Vue is untouched by disabling TypeScript — one language off is not the engine off.
        $this->assertSame(count($all->components()), count($withoutTypeScript->components()));
    }

    public function test_disabling_vue_leaves_the_typescript_modules(): void
    {
        $withoutVue = Codebase::scan(self::FIXTURE, languages: new Languages(Language::Vue));

        $this->assertSame([], $withoutVue->components());
        $this->assertNotSame([], $this->standaloneModules($withoutVue));
    }

    /**
     * And nothing written in a disabled language is TAUGHT: its worked example is not printed, so a
     * PHP-only project never reads a TypeScript example for a rule it still has.
     */
    public function test_a_disabled_languages_example_is_not_published(): void
    {
        $skill = $this->skillNamed('frontend/mirrored-server-type');
        $examples = [MirroredServerTypeDetector::class => [
            new Example(new Comparison('interface MarkerOnlyInTheTypeScriptExample { id: string }'), Language::TypeScript),
            new Example(new Comparison('interface MarkerOnlyInTheVueExample { id: string }'), Language::Vue),
        ]];

        $everything = new SkillRenderer()->render($skill, $examples);
        $noTypeScript = new SkillRenderer(new Languages(Language::TypeScript))->render($skill, $examples);

        $this->assertStringContainsString('MarkerOnlyInTheTypeScriptExample', $everything, 'the TypeScript example publishes by default');
        $this->assertStringContainsString('MarkerOnlyInTheVueExample', $everything);

        $this->assertStringNotContainsString('MarkerOnlyInTheTypeScriptExample', $noTypeScript, 'a language the project does not write is not taught');
        $this->assertStringContainsString('MarkerOnlyInTheVueExample', $noTypeScript, 'the languages it DOES write are untouched');
    }

    private function skillNamed(string $slug): object
    {
        foreach (Skills::all() as $skill) {
            if ($skill->slug === $slug) {
                return $skill;
            }
        }

        $this->fail("no skill {$slug}");
    }

    /**
     * A SHIPPED skill is copied into a project rather than re-rendered, so the filtering has to
     * reach the rendered document too — otherwise `sync` would hand a PHP-only project the
     * TypeScript example that `judge` refuses to enforce.
     */
    public function test_a_published_skill_drops_the_sections_of_a_language_not_written(): void
    {
        $published = <<<'MD'
            ## Bad → good

            ### a-rule — in TypeScript

            ```ts
            MarkerOnlyInTheTypeScriptExample
            ```

            ### a-rule — in Vue

            ```vue
            MarkerOnlyInTheVueExample
            ```

            ## When it fires
            MD;

        $kept = LanguageSections::keep($published, new Languages(Language::TypeScript));

        $this->assertStringNotContainsString('MarkerOnlyInTheTypeScriptExample', $kept);
        $this->assertStringContainsString('MarkerOnlyInTheVueExample', $kept);
        $this->assertStringContainsString('## When it fires', $kept, 'the sections after it survive');

        // A project that writes everything gets the document untouched.
        $this->assertSame($published, LanguageSections::keep($published, new Languages()));
    }

    /**
     * @return list<string>
     */
    private function standaloneModules(Codebase $codebase): array
    {
        $files = [];

        foreach ($codebase->modules() as $module) {
            if (str_ends_with($module->file, '.ts')) {
                $files[] = $module->file;
            }
        }

        return $files;
    }
}
