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

    public function test_a_non_writer_tool_is_ignored(): void
    {
        $payload = ['hook_event_name' => 'PostToolUse', 'tool_name' => 'Bash', 'tool_input' => ['command' => 'ls']];

        $this->assertSame([], $this->fire($payload));
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
