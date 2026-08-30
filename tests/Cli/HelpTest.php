<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Doc\CommandBlocks;
use JesseGall\CodeCommandments\Cli\Doc\CommandTable;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * The CLI documents ITSELF. Every screen — the overview, a command's page, a usage error, the README
 * table and the command references inside the skills — is projected from each {@see Command}'s own
 * `help()`, so a subcommand or flag can never exist undocumented and no second list is kept by hand.
 * These tests hold that: every registered command declares itself, and every form it declares reaches
 * every surface.
 */
final class HelpTest extends TestCase
{
    /**
     * @return list<Command>
     */
    private function commands(): array
    {
        return new Kernel()->commands();
    }

    public function test_every_registered_command_declares_itself(): void
    {
        foreach ($this->commands() as $command) {
            $verb = $command->names()[0];
            $help = $command->help();

            $this->assertNotSame('', trim($help->summary), "`commandments {$verb}` has no help summary — declare one in its help().");
            $this->assertNotEmpty($help->forms, "`commandments {$verb}` declares no invocation form — add a ->form(...) per subcommand.");

            // A form is written as the user types it after `commandments`, so it opens with one of
            // the verbs this command answers to — the renderer supplies the binary name.
            foreach ($help->forms as $form) {
                $opens = array_filter($command->names(), static fn (string $name): bool => str_starts_with($form->syntax, $name));

                $this->assertNotEmpty($opens, "the form `{$form->syntax}` doesn't open with a verb `{$verb}` answers to.");
            }
        }
    }

    public function test_the_overview_lists_every_verb(): void
    {
        $overview = new HelpScreen($this->commands())->overview();

        foreach ($this->commands() as $command) {
            $this->assertStringContainsString($command->names()[0], $overview);
            $this->assertStringContainsString(explode(' ', trim($command->help()->summary))[0], $overview);
        }
    }

    public function test_a_command_page_shows_every_form_and_option(): void
    {
        $until = $this->command('until');
        $page = new HelpScreen($this->commands())->page($until);

        // The regression that started this: `pause`/`resume` existed as subcommands but no help
        // mentioned them, because the screen was hand-maintained.
        $this->assertStringContainsString('stop-condition pause', $page);
        $this->assertStringContainsString('stop-condition resume', $page);

        $judge = new HelpScreen($this->commands())->page($this->command('judge'));

        foreach ($this->command('judge')->help()->options as $option) {
            $this->assertStringContainsString($option->spec, $judge);
        }
    }

    public function test_asking_for_help_prints_the_overview_or_one_page(): void
    {
        $this->assertStringContainsString('Usage:', $this->render(['commandments', '--help']));
        $this->assertStringContainsString('commandments stop-condition', $this->render(['commandments', 'help', 'until']));
        $this->assertStringContainsString('commandments layers add', $this->render(['commandments', 'layers', '--help']));
    }

    public function test_a_usage_error_prints_the_same_page_on_stderr(): void
    {
        $command = $this->command('until');

        ob_start();
        $exit = HelpScreen::usage($command, 'Name the condition.');
        ob_end_clean();

        $this->assertSame(2, $exit, 'a usage error exits 2');
    }

    public function test_the_markdown_table_projects_the_same_forms(): void
    {
        $table = CommandTable::forVerbs('until');

        foreach ($this->command('until')->help()->forms as $form) {
            $this->assertStringContainsString('commandments ' . $form->syntax, $table);
        }

        $this->assertStringContainsString('commandments judge', CommandTable::overview());
    }

    public function test_a_document_block_is_filled_from_the_live_cli(): void
    {
        $document = "# Doc\n\n<!-- BEGIN: commands:stop-condition (auto-generated, run `composer sins`) -->\nstale\n<!-- END: commands:stop-condition -->\n";

        $refreshed = CommandBlocks::refresh($document);

        $this->assertStringNotContainsString('stale', $refreshed);
        $this->assertStringContainsString('commandments stop-condition met <n>', $refreshed);
        $this->assertSame($refreshed, CommandBlocks::refresh($refreshed), 'refreshing twice must be a no-op');
    }

    public function test_every_skill_command_reference_is_current(): void
    {
        $root = dirname(__DIR__, 2) . '/skills';

        foreach (CommandBlocks::documentsIn($root) as $path => $document) {
            $this->assertSame(
                $document,
                CommandBlocks::refresh($document),
                substr($path, strlen($root) + 1) . ' has a stale command reference — run `composer sins`.',
            );
        }
    }

    private function command(string $verb): Command
    {
        foreach ($this->commands() as $command) {
            if (in_array($verb, $command->names(), true)) {
                return $command;
            }
        }

        $this->fail("no command answers to `{$verb}`");
    }

    /**
     * Run the Kernel and capture what it printed.
     *
     * @param  list<string>  $argv
     */
    private function render(array $argv): string
    {
        ob_start();
        $exit = new Kernel()->run($argv);
        $out = (string) ob_get_clean();

        $this->assertSame(0, $exit, 'asking for help succeeds');

        return $out;
    }

    /**
     * Every surface is projected from `help()`, so nothing can drift between the screen, the README and
     * the skills. What NOTHING checked was the declaration against the CODE — and a form is a promise:
     * `plan stale [--for=N]` told a reader the flag existed while the command rejected it as unknown,
     * because it was written as a form and never declared as an option.
     *
     * So a flag mentioned in a form must be one the command actually declares. Both halves live in the
     * same `help()` call, which is what makes this cheap to keep true.
     */
    public function test_a_form_never_promises_a_flag_the_command_does_not_declare(): void
    {
        foreach ($this->commands() as $command) {
            $help = $command->help();
            $declared = $help->optionNames();

            foreach ($help->forms as $form) {
                foreach ($this->flagsIn($form->syntax) as $flag) {
                    $this->assertContains(
                        $flag,
                        $declared,
                        "`{$form->syntax}` promises `--{$flag}`, which {$command->names()[0]} does not declare as an option",
                    );
                }
            }
        }
    }

    /**
     * The long flags a form's syntax mentions, by name — `[--for=N]` is `for`. A form is prose for a
     * human, so this reads the words rather than parsing a grammar.
     *
     * @return list<string>
     */
    private function flagsIn(string $syntax): array
    {
        $flags = [];

        foreach (preg_split('/\s+/', $syntax) ?: [] as $word) {
            $word = trim($word, "[]().,|`'\"");

            if (! str_starts_with($word, '--') || $word === '--') {
                continue;
            }

            $name = Help::nameOf($word);

            if ($name !== '') {
                $flags[] = $name;
            }
        }

        return $flags;
    }
}
