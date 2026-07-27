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

        $this->assertFileExists("{$this->consumer}/.claude/skills/commandments-executing-plans/SKILL.md");

        // Config gained a planExecution block, its onComplete inferred from composer scripts.
        $this->assertSame(
            ['composer test', 'composer lint'],
            Config::load($this->consumer)->planExecutionSettings()->checksFor(Moment::Complete),
        );

        // The plan-reminder hook is wired (via the generic `hook '<class>'` runner) and stamped.
        $settings = (string) file_get_contents("{$this->consumer}/.claude/settings.json");
        $this->assertStringContainsString(' hooks ', $settings, 'the dispatcher entry point is wired');
        $this->assertStringContainsString('@code-commandments-managed', $settings);

        // The published-skills glob covers the flat commandments-* dirs.
        $this->assertStringContainsString('.claude/skills/commandments-*/', (string) file_get_contents("{$this->consumer}/.gitignore"));
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

        Custom::forget();
        $this->sync();
        Custom::forget();

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
