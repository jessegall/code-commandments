<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Sync;
use JesseGall\CodeCommandments\Config;
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
        $this->assertStringContainsString('PlanReminder', $settings);
        $this->assertStringContainsString('@code-commandments-managed', $settings);

        // The published-skills glob covers the flat commandments-* dirs.
        $this->assertStringContainsString('.claude/skills/commandments-*/', (string) file_get_contents("{$this->consumer}/.gitignore"));
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
        $this->assertStringContainsString('FakeHook', $settings, 'the consumer hook is wired');
        $this->assertStringContainsString('Notification', $settings, 'under its declared event');
    }

    private function sync(): void
    {
        ob_start();
        new Sync()->run([]);
        ob_get_clean();
    }
}
