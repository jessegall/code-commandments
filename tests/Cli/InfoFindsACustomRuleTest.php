<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Info;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Make\Make;
use PHPUnit\Framework\TestCase;

/**
 * A project's OWN rule fires with a name the reader is then told to look up — and the lookup failed,
 * because `info` searched only the shipped registry (#469). Those are the rules least likely to be
 * recognised, so they are exactly the ones that must be explainable.
 */
final class InfoFindsACustomRuleTest extends TestCase
{
    private string $dir = '';

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            exec('rm -rf ' . escapeshellarg($this->dir));
        }
    }

    public function test_a_project_own_rule_is_explained_beside_the_shipped_ones(): void
    {
        $this->projectWithItsOwnRule('ComposedInConstructor');

        $output = $this->inProject(static fn (): int => new Info()->run(Input::of('info', ['composed-in-constructor'])));

        $this->assertStringContainsString('composed-in-constructor', $output, 'the sin id resolves');
        $this->assertStringContainsString('Why it is a sin', $output);
    }

    public function test_the_detector_name_off_the_checklist_resolves_too(): void
    {
        $this->projectWithItsOwnRule('BareElementReturn');

        $output = $this->inProject(static fn (): int => new Info()->run(Input::of('info', ['BareElementReturnDetector'])));

        $this->assertStringContainsString('bare-element-return', $output);
    }

    public function test_it_is_named_as_the_projects_own_and_never_offered_for_report(): void
    {
        // A report files against the PACKAGE, which cannot answer for a rule it did not ship.
        $this->projectWithItsOwnRule('UntypedSlotAccess');

        $output = $this->inProject(static fn (): int => new Info()->run(Input::of('info', ['untyped-slot-access'])));

        $this->assertStringContainsString('.commandments/custom/', $output);
        $this->assertStringNotContainsString('commandments report --detector=', $output);
    }

    private function projectWithItsOwnRule(string $name): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-info-custom-' . uniqid('', true);
        mkdir("{$this->dir}/src", 0777, true);
        file_put_contents("{$this->dir}/composer.json", '{"name":"demo/app","autoload":{"psr-4":{"Demo\\\\":"src/"}}}');

        $this->inProject(static fn (): int => new Make()->run(Input::of('make', [$name])));
    }

    /**
     * Run $command from inside the project, capturing what it printed.
     *
     * @param  \Closure(): int  $command
     */
    private function inProject(\Closure $command): string
    {
        $cwd = (string) getcwd();
        chdir($this->dir);

        ob_start();

        try {
            $command();
        } finally {
            $output = (string) ob_get_clean();
            chdir($cwd);
        }

        return $output;
    }
}
