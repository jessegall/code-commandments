<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
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

    public function test_a_hook_the_plugin_settings_keep_quiet_does_not_run(): void
    {
        putenv(JournalHook::QUIET . '=ModelChoiceReminder, SourceReminder');

        try {
            $this->assertSame([], $this->answer([
                'event' => 'hook.PreToolUse',
                'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
                'tool' => ['name' => 'Agent', 'command' => ''],
            ]));
        } finally {
            putenv(JournalHook::QUIET);
        }
    }

    public function test_the_hook_judges_the_agents_project_not_the_folder_it_is_run_from(): void
    {
        $before = (string) getcwd();
        chdir(sys_get_temp_dir());

        try {
            $this->answer([
                'event' => 'hook.PostToolUse',
                'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
                'data' => ['hook' => 'PostToolUse', 'tool' => 'Edit', 'file' => $this->root . '/src/Thing.php'],
            ]);

            $this->assertSame(realpath($this->root), realpath((string) getcwd()), 'the journal runs the plugin from its own folder; the hook works in the agent\'s');
        } finally {
            chdir($before);
        }
    }

    public function test_a_sin_in_an_edit_is_written_to_the_journals_activity(): void
    {
        mkdir($this->root . '/src', 0777, true);
        file_put_contents($this->root . '/src/Thing.vue', "<template>\n    <div>\n        <span v-for=\"item in items\" :key=\"item\" :class=\"{on: item}\">{{ item }}</span>\n    </div>\n</template>\n");
        ConfigFile::inProject($this->root)->scaffoldIfMissing();

        $edited = [
            'event' => 'hook.PostToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'data' => ['hook' => 'PostToolUse', 'tool' => 'Edit', 'file' => $this->root . '/src/Thing.vue'],
        ];
        $answer = $this->answer($edited);

        $this->assertSame('Sin found', $answer['activity']['title'] ?? null);
        $this->assertStringContainsString('src/Thing.vue:3', $answer['activity']['brief']);

        putenv(JournalHook::DATA . '=' . $this->root);
        $this->answer($edited);
        $this->assertSame([], $this->answer(['event' => 'hook.PreToolUse', 'agent' => $edited['agent'], 'tool' => ['name' => 'Edit', 'file' => $this->root . '/src/Thing.vue']]), 'asking before the edit keeps no score');
        file_put_contents($this->root . '/src/Thing.vue', "<template>\n    <div>\n        <template v-for=\"item in items\" :key=\"item\">\n            <span :class=\"{on: item}\">{{ item }}</span>\n        </template>\n    </div>\n</template>\n");
        $answer = $this->answer($edited);
        putenv(JournalHook::DATA);

        $this->assertSame('Sin repented', $answer['activity']['title'] ?? null, 'the edit that clears a file says so');
    }

    public function test_the_journal_owns_the_hooks_once_the_plugin_is_installed(): void
    {
        $this->assertFalse(HookRegistry::journalDriven($this->root), 'a project without the plugin wires its own hooks');

        mkdir($this->root . '/.journal/plugins/code-commandments/.journal-plugin', 0777, true);
        file_put_contents($this->root . '/.journal/plugins/code-commandments/.journal-plugin/plugin.json', '{}');

        $this->assertTrue(HookRegistry::journalDriven($this->root), 'with it installed, the journal drives them');
    }
}
