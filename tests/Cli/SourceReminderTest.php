<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\SourceReminder;
use PHPUnit\Framework\TestCase;

/**
 * The source-over-symptom nudge: when the agent edits a test/stub/fixture that `judge` never scans, it
 * injects "the real fix probably belongs at the SOURCE" — non-blocking, rate-limited, and silent on both
 * a production-code edit and a project that actually judges its tests. Driven through a
 * {@see CapturingHookIO}, no harness or real repo.
 */
final class SourceReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-source-' . uniqid('', true);
        @mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/.commandments/.source-remind-count');
        @unlink($this->root . '/.commandments/config.php');
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_editing_a_test_file_nudges_toward_the_source(): void
    {
        $context = $this->context($this->editing('Edit', $this->root . '/tests/Unit/OrderTest.php'));

        $this->assertStringContainsString('OrderTest.php', $context);
        $this->assertStringContainsString('trace to the source', $context);
        $this->assertStringContainsString('judge', $context);
    }

    public function test_a_stub_and_a_fixture_and_a_spec_all_count_as_symptom_surfaces(): void
    {
        $this->assertNotSame([], $this->editing('Write', $this->root . '/database/factories/UserData.stub'));
        $this->resetCounter();
        $this->assertNotSame([], $this->editing('Edit', $this->root . '/src/__mocks__/api.ts'));
        $this->resetCounter();
        $this->assertNotSame([], $this->editing('Edit', $this->root . '/resources/js/order.spec.ts'));
    }

    public function test_editing_production_code_is_silent(): void
    {
        $this->assertSame([], $this->editing('Edit', $this->root . '/src/Editor/Ui/UiElement.php'));
        // `src/Contest/` must not trip the `test` segment match.
        $this->assertSame([], $this->editing('Edit', $this->root . '/src/Contest/Entry.php'));
    }

    public function test_a_non_writer_tool_is_ignored(): void
    {
        $payload = ['hook_event_name' => 'PreToolUse', 'tool_name' => 'Bash', 'tool_input' => ['command' => 'phpunit']];
        $this->assertSame([], $this->fire($payload));
    }

    public function test_a_project_that_judges_its_tests_gets_no_nudge(): void
    {
        // The whole repo is a source root → the test file IS judged → no blind spot, so stay silent.
        $this->writeConfig("\$config->paths('.');");

        $this->assertSame([], $this->editing('Edit', $this->root . '/tests/Unit/OrderTest.php'));
    }

    public function test_it_fires_on_the_first_edit_then_rate_limits(): void
    {
        // First symptom edit of the session fires; the next several are silent (every-10 cadence).
        $this->assertNotSame([], $this->editing('Edit', $this->root . '/tests/A.test.ts'), 'the first fires');

        for ($i = 0; $i < 8; $i++) {
            $this->assertSame([], $this->editing('Edit', $this->root . '/tests/A.test.ts'), "edit {$i} is quiet");
        }

        $this->assertNotSame([], $this->editing('Edit', $this->root . '/tests/A.test.ts'), 'the 10th fires again');
    }

    private function resetCounter(): void
    {
        @unlink($this->root . '/.commandments/.source-remind-count');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function editing(string $tool, string $file): array
    {
        return $this->fire([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => $tool,
            'tool_input' => ['file_path' => $file],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function fire(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, 'sha', 'main'), $payload);
        new SourceReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * @param  list<array<string, mixed>>  $emitted
     */
    private function context(array $emitted): string
    {
        return (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
    }

    private function writeConfig(string $body): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    {$body}\n};\n",
        );
    }
}
