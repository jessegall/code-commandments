<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Hooks\Handlers\SkillReminder;
use JesseGall\CodeCommandments\WholeTree;
use PHPUnit\Framework\TestCase;

/**
 * The nudge that names a skill for the code just written. A skill's description can only say when a
 * discipline is PROBABLY relevant; the detectors can say that it definitely is — so an edit that
 * breaks a rule is answered with that rule's skill, then and there.
 */
final class SkillReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-skill-' . uniqid('', true);
        @mkdir($this->root . '/src', 0777, true);
        @mkdir($this->root . '/tests', 0777, true);
        @mkdir($this->root . '/.commandments', 0777, true);
        file_put_contents($this->root . '/.commandments/config.php', "<?php\n\nreturn function (\$config): void {\n    \$config->paths('src');\n};\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_an_edit_that_breaks_a_rule_names_the_skill_that_fixes_it(): void
    {
        // A history comment: `archaeology-comment`, taught by `backend/documentation`. One file is all
        // the rule needs, which is exactly why it is not marked WholeTree.
        $this->write('src/Importer.php', <<<'PHP'
            <?php

            final class Importer
            {
                public function run(): void
                {
                    // formerly lived inline in the controller; was extracted here
                    $this->go();
                }

                private function go(): void {}
            }
            PHP);

        $context = $this->context($this->editing('Edit', 'src/Importer.php'));

        $this->assertStringContainsString('Importer.php', $context);
        $this->assertStringContainsString('archaeology-comment', $context);
        $this->assertStringContainsString('commandments-backend-documentation', $context);
        $this->assertStringContainsString('SOURCE', $context, 'and it points at the source, not the symptom');
    }

    public function test_clean_code_is_silent(): void
    {
        $this->write('src/Clean.php', <<<'PHP'
            <?php

            final class Clean
            {
                public function greet(string $name): string
                {
                    return "hello {$name}";
                }
            }
            PHP);

        $this->assertSame([], $this->editing('Edit', 'src/Clean.php'));
    }

    public function test_a_file_judge_does_not_scan_is_left_alone(): void
    {
        $this->write('tests/ImporterTest.php', <<<'PHP'
            <?php

            final class ImporterTest
            {
                public function test_it(): void
                {
                    // formerly lived inline in the controller; was extracted here
                }
            }
            PHP);

        $this->assertSame([], $this->editing('Edit', 'tests/ImporterTest.php'), 'outside the declared source roots');
    }

    public function test_a_file_written_by_the_shell_is_checked_too(): void
    {
        // A `Write` names its `file_path`; a heredoc, a `sed -i` or a script names nothing, and
        // reading the command to guess a path would be a parser telling itself stories. So a shell
        // command is answered with the judged files that CHANGED since the last one.
        $this->shell('ls');

        $this->write('src/Shipper.php', <<<'PHP'
            <?php

            final class Shipper
            {
                public function run(): void
                {
                    // formerly lived inline in the controller; was extracted here
                    $this->go();
                }

                private function go(): void {}
            }
            PHP);

        $context = $this->context($this->shell("cat > src/Shipper.php <<'EOF'\n…\nEOF"));

        $this->assertStringContainsString('archaeology-comment', $context);
        $this->assertStringContainsString('Shipper.php', $context);
        $this->assertStringContainsString('commandments-backend-documentation', $context);
    }

    public function test_a_shell_command_that_changed_nothing_is_silent(): void
    {
        $this->write('src/Clean.php', <<<'PHP'
            <?php

            final class Clean
            {
                public function greet(string $name): string
                {
                    return "hello {$name}";
                }
            }
            PHP);

        $this->shell('ls');

        $this->assertSame([], $this->shell('git status'), 'nothing was written between the two');
    }

    public function test_the_first_shell_command_of_a_session_claims_nothing(): void
    {
        // There is no "since" yet, and a tree of files nobody touched is not news.
        $this->write('src/Legacy.php', <<<'PHP'
            <?php

            final class Legacy
            {
                public function run(): void
                {
                    // formerly lived inline in the controller; was extracted here
                }
            }
            PHP);

        $this->assertSame([], $this->shell('ls'));
    }

    public function test_it_never_asks_a_rule_that_needs_the_whole_tree(): void
    {
        $single = Catalog::singleFile();

        $this->assertNotSame([], $single);

        foreach ($single as $detector) {
            $this->assertNotInstanceOf(WholeTree::class, $detector, $detector::class . ' cannot answer about one file');
        }

        // And the marker is actually in use — otherwise the guard is a no-op nobody would notice.
        $this->assertLessThan(count(Catalog::all()), count($single));
    }

    public function test_a_docblock_reference_to_a_class_declared_elsewhere_is_not_dangling(): void
    {
        // Whether a `{@see}` dangles is a question about the WHOLE codebase — the class it names
        // lives in another file. Asked about one file, the rule can only answer "no such class",
        // and a correct reference is reported as rot the agent is told to go and fix.
        $this->write('src/Registry.php', <<<'PHP'
            <?php

            namespace App;

            final class Registry {}
            PHP);

        $this->write('src/Loader.php', <<<'PHP'
            <?php

            namespace App;

            /**
             * Reads what {@see \App\Registry} holds.
             */
            final class Loader
            {
                public function load(): string
                {
                    return 'loaded';
                }
            }
            PHP);

        $this->assertSame([], $this->editing('Edit', 'src/Loader.php'), 'the class it names is declared next door');
    }

    private function write(string $relative, string $contents): void
    {
        file_put_contents($this->root . '/' . $relative, $contents . "\n");
    }

    /**
     * @return list<object>
     */
    private function editing(string $tool, string $relative): array
    {
        return $this->fire([
            'hook_event_name' => 'PostToolUse',
            'tool_name' => $tool,
            'tool_input' => ['file_path' => $this->root . '/' . $relative],
        ]);
    }

    /**
     * @return list<object>
     */
    private function shell(string $command): array
    {
        return $this->fire([
            'hook_event_name' => 'PostToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => $command],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<object>
     */
    private function fire(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, 'sha', 'main'), $payload);
        new SkillReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * @param  list<object>  $emitted
     */
    private function context(array $emitted): string
    {
        $this->assertNotSame([], $emitted, 'the hook stayed silent');

        return $emitted[0]->context->unwrapOr('');
    }
}
