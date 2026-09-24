<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Hooks\JournalHook;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryProject;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The entry point the agent journal's plugin calls: a moment arrives in the journal's shape, the same
 * handlers run, and the answer goes back in the journal's shape — `refuse` to stop the call, `whisper`
 * to tell the agent alone.
 */
final class JournalHookTest extends TestCase
{
    use TemporaryProject;

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

    public function test_a_moment_naming_no_event_runs_no_handler(): void
    {
        $io = new CapturingHookIO(new class extends GitFiles {
            public function root(string $path): ?string
            {
                throw new LogicException('a moment with no event must not read git');
            }
        }, ['tool' => 'Bash', 'input' => ['command' => 'ls']]);

        ob_start();
        new JournalHook($io)->run(Input::of('journal-hook'));

        $this->assertSame('{}', trim((string) ob_get_clean()));
    }

    public function test_a_moment_no_handler_cares_about_answers_with_nothing(): void
    {
        $this->assertSame([], $this->answer([
            'event' => 'hook.PreToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'tool' => ['name' => 'Read', 'file' => 'src/Thing.php'],
        ]));
    }

    public function test_a_dispatch_the_journal_names_without_its_input_is_not_reminded_of_a_model(): void
    {
        $this->assertSame([], $this->answer([
            'event' => 'hook.PreToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'tool' => ['name' => 'Agent', 'command' => ''],
        ]));
    }

    public function test_advice_waits_for_the_advising_run_and_gates_answer_alone(): void
    {
        $moment = [
            'event' => 'hook.PreToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'tool' => ['name' => 'Edit', 'file' => $this->root . '/tests/OrderTest.php'],
        ];
        $hook = new JournalHook(new CapturingHookIO(new FakeGit($this->root, 'sha1', 'feature/x'), $moment));

        $this->assertSame('{}', $hook->gateAnswerFor($moment)->toJson(), 'the source reminder advises, so the gate-only answer holds nothing');
        $this->assertNotSame('', (string) $hook->adviceFor($moment)->whisper);
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

    public function test_a_sin_in_an_edit_raises_sin_found_and_clearing_it_raises_sin_resolved(): void
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

        $this->assertSame('sin-found', $answer['raise'][0]['event'] ?? null);
        $this->assertStringContainsString('src/Thing.vue:3', $answer['raise'][0]['brief']);

        putenv(JournalHook::DATA . '=' . $this->root);
        $this->answer($edited);
        $this->assertArrayNotHasKey('raise', $this->answer($edited), 'the same sins in the same file are not found twice');
        $this->assertSame([], $this->answer(['event' => 'hook.PreToolUse', 'agent' => $edited['agent'], 'tool' => ['name' => 'Edit', 'file' => $this->root . '/src/Thing.vue']]), 'asking before the edit keeps no score');
        file_put_contents($this->root . '/src/Thing.vue', "<template>\n    <div>\n        <template v-for=\"item in items\" :key=\"item\">\n            <span :class=\"{on: item}\">{{ item }}</span>\n        </template>\n    </div>\n</template>\n");
        $answer = $this->answer($edited);
        putenv(JournalHook::DATA);

        $this->assertSame('sin-resolved', $answer['raise'][0]['event'] ?? null, 'the edit that clears a file says so');
    }

    public function test_a_sin_is_repented_only_when_its_file_edited_again_no_longer_holds_it(): void
    {
        mkdir($this->root . '/src', 0777, true);
        $sinful = "        <span v-for=\"item in items\" :key=\"item\" :class=\"{on: item}\">{{ item }}</span>\n";
        file_put_contents($this->root . '/src/Thing.vue', "<template>\n    <div>\n{$sinful}    </div>\n</template>\n");
        file_put_contents($this->root . '/src/Other.vue', "<template>\n    <p>other</p>\n</template>\n");
        ConfigFile::inProject($this->root)->scaffoldIfMissing();
        putenv(JournalHook::DATA . '=' . $this->root);

        $editing = fn (string $file): array => [
            'event' => 'hook.PostToolUse',
            'agent' => ['session' => 'claude-1', 'cwd' => $this->root],
            'data' => ['hook' => 'PostToolUse', 'tool' => 'Edit', 'file' => "{$this->root}/src/{$file}"],
        ];

        try {
            $this->assertSame('sin-found', $this->answer($editing('Thing.vue'))['raise'][0]['event'] ?? null);

            file_put_contents($this->root . '/src/Thing.vue', "<template>\n    <div>\n        <h1>moved down</h1>\n{$sinful}    </div>\n</template>\n");
            $this->assertArrayNotHasKey('raise', $this->answer($editing('Thing.vue')), 'a sin whose line moved is the same sin, neither found again nor repented');

            $this->assertArrayNotHasKey('raise', $this->answer($editing('Other.vue')), 'editing another file proves nothing about this one');
        } finally {
            putenv(JournalHook::DATA);
        }
    }

    public function test_a_moment_moves_session_folders_left_in_the_project_into_the_plugins_data_folder(): void
    {
        mkdir("{$this->root}/.commandments/sessions/old01", 0777, true);
        file_put_contents("{$this->root}/.commandments/sessions/old01/note", 'kept');
        mkdir("{$this->root}/.journal/plugins/code-commandments/.journal-plugin", 0777, true);
        file_put_contents("{$this->root}/.journal/plugins/code-commandments/.journal-plugin/plugin.json", '{}');

        $this->answer(['event' => 'hook.PreToolUse', 'agent' => ['session' => 'claude-1', 'cwd' => $this->root], 'tool' => ['name' => 'Read']]);

        $this->assertSame('kept', file_get_contents("{$this->root}/.journal/plugin-data/code-commandments/sessions/old01/note"));
    }

    public function test_the_journal_owns_the_hooks_once_the_plugin_is_installed(): void
    {
        $this->assertFalse(HookRegistry::journalDriven($this->root), 'a project without the plugin wires its own hooks');

        mkdir($this->root . '/.journal/plugins/code-commandments/.journal-plugin', 0777, true);
        file_put_contents($this->root . '/.journal/plugins/code-commandments/.journal-plugin/plugin.json', '{}');

        $this->assertTrue(HookRegistry::journalDriven($this->root), 'with it installed, the journal drives them');
    }
}
