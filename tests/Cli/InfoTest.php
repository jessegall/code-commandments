<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Info;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\SinLookup;
use JesseGall\CodeCommandments\Cli\SkillSection;
use PHPUnit\Framework\TestCase;

/**
 * `info` answers "what is this finding and why do I care" from the checklist's own words — the
 * detector name — so every spelling of a rule has to reach it.
 */
final class InfoTest extends TestCase
{
    public function test_a_rule_is_found_by_every_name_it_is_written_under(): void
    {
        foreach (['array-bag', 'ArrayBag', 'ArrayBagDetector', 'arraybag', 'array_bag'] as $spelling) {
            $found = array_map(
                static fn (object $detector): string => new \ReflectionClass($detector)->getShortName(),
                SinLookup::detectors($spelling),
            );

            $this->assertContains('ArrayBagDetector', $found, "`{$spelling}` should reach the rule");
        }
    }

    public function test_it_explains_a_rule(): void
    {
        $output = $this->explain(['array-bag']);

        $this->assertStringContainsString('array-bag', $output);
        $this->assertStringContainsString('backend/value-objects', $output);
        $this->assertStringContainsString('Why it is a sin', $output);
        $this->assertStringContainsString('How to fix it', $output);
        $this->assertStringContainsString('commandments-backend-value-objects', $output, 'it names the skill to load');
        $this->assertStringContainsString('commandments judge --sin=array-bag', $output);
        $this->assertStringContainsString('commandments disable array-bag', $output);
    }

    public function test_it_shows_the_skills_own_worked_example(): void
    {
        $output = $this->explain(['array-bag']);

        $this->assertStringContainsString('Example', $output);
        $this->assertStringContainsString('----------[ Bad ]----------', $output);
        $this->assertStringContainsString('----------[ Good ]----------', $output);
    }

    public function test_it_takes_the_name_as_a_flag_too(): void
    {
        $this->assertStringContainsString('array-bag', $this->explain(['--sin=array-bag']));
        $this->assertStringContainsString('array-bag', $this->explain(['--detector=ArrayBagDetector']));
    }

    public function test_an_unknown_rule_offers_names_rather_than_nothing(): void
    {
        $this->assertSame(2, $this->exitCode(['no-such-rule-anywhere']));
    }

    /**
     * A rule taught in more than one language shows the example of EACH, labelled — the same
     * per-language split the published skill has.
     */
    public function test_a_two_language_rule_shows_both_examples(): void
    {
        $output = $this->explain(['mirrored-server-type']);

        $this->assertStringContainsString('In TypeScript:', $output);
        $this->assertStringContainsString('In Vue:', $output);
    }

    public function test_the_section_reader_keeps_only_the_named_sins_block(): void
    {
        $document = "## Bad → good\n\n### wanted\n\nprose\n\n```php\nKEEP\n```\n\n### other\n\n```php\nDROP\n```\n\n## When it fires\n";

        $section = SkillSection::forSin($document, 'wanted');

        $this->assertStringContainsString('KEEP', $section);
        $this->assertStringNotContainsString('DROP', $section);
        $this->assertStringNotContainsString('prose', $section, 'the description is shown above, not twice');
    }

    /**
     * @param  list<string>  $arguments
     */
    private function explain(array $arguments): string
    {
        ob_start();

        try {
            new Info()->run(Input::of('info', $arguments));
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function exitCode(array $arguments): int
    {
        ob_start();

        try {
            return new Info()->run(Input::of('info', $arguments));
        } finally {
            ob_end_clean();
        }
    }
}
