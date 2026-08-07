<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Sync;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Moment;
use PHPUnit\Framework\TestCase;

/**
 * `commandments sync` wires a consumer end-to-end: it publishes the standalone `executing-plans`
 * skill, injects a `planExecution()` block inferred from the project's scripts, and self-heals the
 * hooks — all idempotently. Exercised in a temp consumer dir the process cd's into.
 */
final class SyncTest extends TestCase
{
    private string $consumer;

    private string $cwd;

    protected function setUp(): void
    {
        $this->consumer = sys_get_temp_dir() . '/cc-sync-' . uniqid('', true);
        @mkdir($this->consumer, 0777, true);
        file_put_contents("{$this->consumer}/composer.json", json_encode(['scripts' => ['test' => 'phpunit', 'lint' => 'pint']]));

        $this->cwd = (string) getcwd();
        chdir($this->consumer);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        exec('rm -rf ' . escapeshellarg($this->consumer));
    }

    public function test_sync_publishes_the_skill_injects_the_config_and_wires_the_hooks(): void
    {
        $this->sync();

        // The skill is a REAL file in the library, and the agent's folder is a link to it. Asserting
        // only that the path is readable would pass just as well if the link were a silent copy —
        // the whole single-source property could be dead and the test still green.
        $this->assertFileExists("{$this->consumer}/.agents/skills/commandments-executing-plans/SKILL.md");
        $this->assertTrue(is_link("{$this->consumer}/.claude/skills/commandments-executing-plans"));
        $this->assertSame(
            realpath("{$this->consumer}/.agents/skills/commandments-executing-plans"),
            realpath("{$this->consumer}/.claude/skills/commandments-executing-plans"),
        );

        // Config gained a planExecution block, its onComplete inferred from composer scripts.
        $this->assertSame(
            ['composer test', 'composer lint'],
            Config::load($this->consumer)->planExecutionSettings()->checksFor(Moment::Complete),
        );

        // The plan-reminder hook is wired (via the generic `hook '<class>'` runner) and stamped.
        $settings = (string) file_get_contents("{$this->consumer}/.claude/settings.json");
        $this->assertStringContainsString(' hooks ', $settings, 'the dispatcher entry point is wired');
        $this->assertStringContainsString('@code-commandments-managed', $settings);

        $ignore = (string) file_get_contents("{$this->consumer}/.gitignore");
        $this->assertStringContainsString('.agents/skills/commandments-*/', $ignore);
        // WITHOUT a trailing slash: a slash means "directory only", and git records a symlink as a
        // file — so the slashed form stopped ignoring the published skills the moment they became
        // links, and every one of them would show up untracked.
        $this->assertStringContainsString(".claude/skills/commandments-*\n", $ignore);
        $this->assertStringNotContainsString(".claude/skills/commandments-*/\n", $ignore);
    }

    public function test_a_project_on_the_old_layout_migrates_in_one_sync(): void
    {
        // Everything a consumer wired by an older release has: skills COPIED into the agent's own
        // folder, the briefing inline in CLAUDE.md under the markers that release used, and the
        // ignore rule whose trailing slash no longer matches. None of it should need a human.
        $copied = "{$this->consumer}/.claude/skills/commandments-executing-plans";
        @mkdir($copied, 0775, true);
        file_put_contents("{$copied}/SKILL.md", "an old release's copy\n");
        file_put_contents("{$this->consumer}/.gitignore", "/vendor\n\n# code-commandments published skills (regenerated on composer update)\n.claude/skills/commandments-*/\n");
        file_put_contents("{$this->consumer}/CLAUDE.md", "# My project\n\nmy own standing orders\n\n"
            . "<!-- BEGIN: code-commandments skills (auto-generated, run `composer update`) -->\nthe old inline briefing\n<!-- END: code-commandments skills -->\n\nmore of my own\n");

        $this->sync();

        $claude = (string) file_get_contents("{$this->consumer}/CLAUDE.md");

        $this->assertTrue(is_link($copied), 'the copied directory became a link');
        $this->assertFileExists("{$this->consumer}/.agents/skills/commandments-executing-plans/SKILL.md");
        $this->assertStringContainsString('@AGENTS.md', $claude, 'the inline briefing became an import of the canon');
        $this->assertStringNotContainsString('the old inline briefing', $claude);
        $this->assertStringContainsString("# My project\n\nmy own standing orders\n", $claude, "and the project's own words are untouched");
        $this->assertStringContainsString("more of my own\n", $claude);
        $this->assertStringContainsString('TRACE TO THE SOURCE', (string) file_get_contents("{$this->consumer}/AGENTS.md"));
        $this->assertStringNotContainsString(".claude/skills/commandments-*/\n", (string) file_get_contents("{$this->consumer}/.gitignore"));
        $this->assertStringContainsString("/vendor\n", (string) file_get_contents("{$this->consumer}/.gitignore"), "the project's own ignore lines survive");
    }

    public function test_a_second_sync_changes_nothing_at_all(): void
    {
        $this->sync();
        $before = $this->fingerprint();

        $this->sync();

        // Not just "the config is untouched": a sync that deleted every published skill and wrote
        // none back would have passed that. This is every file we manage, byte for byte.
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_a_hand_written_skill_of_the_projects_own_is_never_swept(): void
    {
        // `.agents/skills` is a shared folder — a project may keep its own skills beside ours, and
        // one of them may perfectly well be named like ours.
        @mkdir("{$this->consumer}/.agents/skills/commandments-mine", 0775, true);
        file_put_contents("{$this->consumer}/.agents/skills/commandments-mine/SKILL.md", "mine, not yours\n");

        $this->sync();
        $this->sync();

        $this->assertSame("mine, not yours\n", file_get_contents("{$this->consumer}/.agents/skills/commandments-mine/SKILL.md"));
    }

    /**
     * Every file this package manages in the consumer, with its contents — links by what they point
     * at rather than what they resolve to, so a link quietly becoming a copy shows up as a change.
     *
     * @return array<string, string>
     */
    private function fingerprint(): array
    {
        $found = [];

        foreach (['.agents', '.claude', '.commandments'] as $dir) {
            if (! is_dir("{$this->consumer}/{$dir}")) {
                continue;
            }

            /**
             * @var \SplFileInfo $file
             */
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("{$this->consumer}/{$dir}", \FilesystemIterator::SKIP_DOTS),
            ) as $file) {
                $path = substr($file->getPathname(), strlen($this->consumer) + 1);
                $found[$path] = $file->isLink() ? 'link:' . readlink($file->getPathname()) : (string) md5_file($file->getPathname());
            }
        }

        foreach (['AGENTS.md', 'CLAUDE.md', '.gitignore'] as $file) {
            if (is_file("{$this->consumer}/{$file}")) {
                $found[$file] = (string) md5_file("{$this->consumer}/{$file}");
            }
        }

        ksort($found);

        return $found;
    }

    public function test_sync_removes_legacy_flat_state_files(): void
    {
        @mkdir("{$this->consumer}/.commandments", 0777, true);
        file_put_contents("{$this->consumer}/.commandments/sins.md", "old\n");
        file_put_contents("{$this->consumer}/.commandments/sins-2026-07-04_101112.md", "older\n");
        file_put_contents("{$this->consumer}/.commandments/.plan-active", "sha\n");
        file_put_contents("{$this->consumer}/.commandments/.cardinal-remind-count", "7\n");

        $this->sync();

        $this->assertFileDoesNotExist("{$this->consumer}/.commandments/sins.md", 'the pre-session flat checklist is migrated out');
        $this->assertFileDoesNotExist("{$this->consumer}/.commandments/sins-2026-07-04_101112.md");
        $this->assertFileDoesNotExist("{$this->consumer}/.commandments/.plan-active");
        $this->assertFileDoesNotExist("{$this->consumer}/.commandments/.cardinal-remind-count");
        $this->assertFileExists("{$this->consumer}/.commandments/config.php", 'the durable config is never touched');
    }

    public function test_a_second_sync_leaves_the_config_untouched(): void
    {
        $this->sync();
        $before = (string) file_get_contents("{$this->consumer}/.commandments/config.php");

        $this->sync();

        $this->assertSame($before, (string) file_get_contents("{$this->consumer}/.commandments/config.php"));
    }

    public function test_a_consumer_registered_hook_is_wired_by_sync(): void
    {
        // A config that registers its own hook — sync must wire it alongside the built-ins.
        @mkdir("{$this->consumer}/.commandments", 0777, true);
        file_put_contents(
            "{$this->consumer}/.commandments/config.php",
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->hook(\\JesseGall\\CodeCommandments\\Tests\\Cli\\FakeHook::class);\n};\n",
        );

        $this->sync();

        $settings = (string) file_get_contents("{$this->consumer}/.claude/settings.json");
        $this->assertStringContainsString('Notification', $settings, 'the consumer hook adds its declared moment');
        $this->assertStringContainsString(' hooks ', $settings, 'wired through the dispatcher entry point');
    }

    public function test_sync_publishes_the_projects_OWN_skill_through_the_same_renderer(): void
    {
        // A project skill + its sin, written where `make` writes them. Sync must render the
        // SKILL.md the finding points at — the custom side is not a lesser citizen.
        $class = 'Cc' . str_replace('.', '', uniqid('', true));
        @mkdir("{$this->consumer}/.commandments/custom", 0777, true);
        file_put_contents("{$this->consumer}/.commandments/custom/{$class}.php", <<<PHP
        <?php
        use JesseGall\\CodeCommandments\\Sins\\Sin;
        use JesseGall\\CodeCommandments\\Skills\\Skill;
        use JesseGall\\CodeCommandments\\Skills\\Tier;

        final class {$class} extends Skill
        {
            public function __construct() { parent::__construct(slug: 'backend/house-style', tier: Tier::KeepInMind, order: 99); }
            public function title(): string { return 'The house style'; }
            public function trigger(): string { return 'Read this when …'; }
            public function intro(): string { return 'Say it once.'; }
            public function summary(): string { return 'say it once.'; }
            public function principle(): string { return 'Because repetition rots.'; }
        }

        final class {$class}Sin extends Sin
        {
            public function __construct()
            {
                parent::__construct(name: 'said-twice', skill: {$class}::class, description: 'It was said twice.', rule: 'Say it once.');
            }
        }
        PHP);

        $this->sync();

        $skill = "{$this->consumer}/.claude/skills/commandments-backend-house-style/SKILL.md";

        $this->assertFileExists($skill, 'the project skill is published where judge points the agent');

        $rendered = (string) file_get_contents($skill);
        $this->assertStringContainsString('# The house style', $rendered);
        $this->assertStringContainsString('Because repetition rots.', $rendered);
        $this->assertStringContainsString('It was said twice.', $rendered, 'its own sin projects into "when it fires"');
        $this->assertStringContainsString('- Say it once.', $rendered, 'and into the rules');
    }

    public function test_the_commandments_gitignore_keeps_the_projects_own_rules_tracked(): void
    {
        $this->sync();

        $ignore = (string) file_get_contents("{$this->consumer}/.commandments/.gitignore");

        $this->assertStringContainsString("!custom/\n", $ignore, 'the directory is re-admitted');
        $this->assertStringContainsString("!custom/**\n", $ignore, 'and so is everything in it');
        $this->assertStringContainsString("!config.php\n", $ignore);
    }

    private function sync(): void
    {
        ob_start();
        new Sync()->run(Input::of('sync'));
        ob_get_clean();
    }
}
