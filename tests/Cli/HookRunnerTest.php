<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\HookRunner;
use PHPUnit\Framework\TestCase;

/**
 * `commandments hook <FQCN>` is the entry point every wired hook runs through — it must run a real
 * {@see \JesseGall\CodeCommandments\Cli\Hook} and reject anything else cleanly (a mistyped/stale
 * wiring), never fatal.
 */
final class HookRunnerTest extends TestCase
{
    public function test_it_runs_a_hook_class(): void
    {
        // FakeHook binds to Notification and does nothing on a manual run → exit 0.
        $this->assertSame(0, $this->exec(FakeHook::class));
    }

    public function test_a_non_hook_class_is_rejected(): void
    {
        $this->assertSame(2, $this->exec(self::class), 'a non-Hook class is not runnable');
    }

    public function test_an_unknown_class_is_rejected(): void
    {
        $this->assertSame(2, $this->exec('Not\A\Real\Class'));
    }

    public function test_a_missing_argument_is_rejected(): void
    {
        $this->assertSame(2, $this->exec());
    }

    private function exec(string ...$args): int
    {
        ob_start();
        $code = new HookRunner()->run($args);
        ob_get_clean();

        return $code;
    }
}
