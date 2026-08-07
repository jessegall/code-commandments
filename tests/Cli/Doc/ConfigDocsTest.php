<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Doc;

use JesseGall\CodeCommandments\Cli\Doc\AgentCatalog;
use JesseGall\CodeCommandments\Cli\Doc\CommandTable;
use JesseGall\CodeCommandments\Cli\Doc\HookCatalog;
use JesseGall\CodeCommandments\Cli\Doc\PlanExecutionOptions;
use PHPUnit\Framework\TestCase;

/**
 * The plan-execution options and the wired hooks document THEMSELVES: their README tables are generated
 * from the `PlanExecution` builder and the `HookRegistry` builtins. These tests enforce it — every option
 * and every hook must carry a summary, and the README section must match the generated table — so a new
 * builder method or hook can't ship undocumented or with a stale README.
 */
final class ConfigDocsTest extends TestCase
{
    public function test_every_plan_execution_option_has_a_summary(): void
    {
        $options = PlanExecutionOptions::all();

        $this->assertNotEmpty($options, 'no options were reflected off the builder');

        foreach ($options as $option) {
            $this->assertNotSame(
                '',
                trim($option['summary']),
                "`PlanExecution::{$option['name']}()` has no docblock summary — document it so the README table can.",
            );
        }
    }

    public function test_every_builtin_hook_has_a_summary_and_events(): void
    {
        $hooks = HookCatalog::all();

        $this->assertNotEmpty($hooks);

        foreach ($hooks as $hook) {
            $this->assertNotSame('', trim($hook['summary']), "the `{$hook['name']}` hook has no summary() — document it.");
            $this->assertNotSame('', trim($hook['events']), "the `{$hook['name']}` hook binds no events.");
        }
    }

    public function test_the_readme_embeds_the_current_generated_tables(): void
    {
        $this->assertSame(
            trim(PlanExecutionOptions::table()),
            $this->marker('plan-options'),
            'the README plan-options table is stale — run `composer readme`.',
        );

        $this->assertSame(
            trim(HookCatalog::table()),
            $this->marker('hooks-table'),
            'the README hooks table is stale — run `composer readme`.',
        );

        $this->assertSame(
            trim(AgentCatalog::table()),
            $this->marker('agents-table'),
            'the README agents table is stale — run `composer readme`.',
        );

        // The command table is projected from every command's own `help()`, so an edit there
        // silently staled the README until this held it: nothing else in the suite reads it.
        $this->assertSame(
            trim(CommandTable::overview()),
            $this->marker('commands-table'),
            'the README command table is stale — run `composer readme`.',
        );
    }

    private function marker(string $name): string
    {
        $readme = (string) file_get_contents(__DIR__ . '/../../../README.md');

        $this->assertSame(1, preg_match(
            '/<!-- BEGIN: ' . preg_quote($name, '/') . '[^>]*-->(.*?)<!-- END: ' . preg_quote($name, '/') . ' -->/s',
            $readme,
            $matches,
        ), "the {$name} markers are missing from README.md");

        return trim($matches[1]);
    }
}
