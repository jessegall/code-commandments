<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks\JournalHook;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The entry point the agent journal's plugin calls: a moment arrives in the journal's shape, the same
 * handlers run, and the answer goes back in the journal's shape — `refuse` to stop the call, `whisper`
 * to tell the agent alone.
 */
final class JournalHookTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-journal-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function answer(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, 'sha1', 'feature/x'), $payload);

        ob_start();
        new JournalHook($io)->run(Input::of('journal-hook'));
        $printed = (string) ob_get_clean();

        $given = json_decode($printed, true);

        return is_array($given) ? $given : [];
    }

    public function test_a_moment_no_handler_cares_about_answers_with_nothing(): void
    {
        $this->assertSame([], $this->answer([
            'event' => 'hook.PreToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'tool' => ['name' => 'Read', 'file' => 'src/Thing.php'],
        ]));
    }

    public function test_a_dispatch_without_a_model_is_whispered_to_the_agent(): void
    {
        $answer = $this->answer([
            'event' => 'hook.PreToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'tool' => ['name' => 'Agent', 'command' => ''],
        ]);

        $this->assertArrayHasKey('whisper', $answer);
        $this->assertStringContainsString('names no model', $answer['whisper']);
        $this->assertArrayNotHasKey('refuse', $answer, 'a reminder never stops the call');
    }

    public function test_the_journal_owns_the_hooks_once_the_plugin_is_installed(): void
    {
        $this->assertFalse(HookRegistry::journalDriven($this->root), 'a project without the plugin wires its own hooks');

        mkdir($this->root . '/.journal/plugins/code-commandments/.journal-plugin', 0777, true);
        file_put_contents($this->root . '/.journal/plugins/code-commandments/.journal-plugin/plugin.json', '{}');

        $this->assertTrue(HookRegistry::journalDriven($this->root), 'with it installed, the journal drives them');
    }
}
