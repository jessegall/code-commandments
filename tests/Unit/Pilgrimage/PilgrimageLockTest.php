<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageLock;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use PHPUnit\Framework\TestCase;

class PilgrimageLockTest extends TestCase
{
    private string $dir;

    private string|false $previousSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-lock-' . uniqid();
        mkdir($this->dir . '/.commandments', 0755, true);
        $this->previousSession = getenv('CLAUDE_CODE_SESSION_ID');
    }

    protected function tearDown(): void
    {
        $this->previousSession === false
            ? putenv('CLAUDE_CODE_SESSION_ID')
            : putenv('CLAUDE_CODE_SESSION_ID=' . $this->previousSession);

        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function runner(): PilgrimageRunner
    {
        $config = ['scrolls' => ['backend' => [
            'extensions' => ['php'],
            'prophets' => [LongMethodProphet::class, PreferOptionOverNullProphet::class],
        ]]];

        return new PilgrimageRunner($this->dir, $config, 'backend');
    }

    public function test_blocks_only_the_owning_session(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');
        (new PilgrimageState(scope: ['/a.php'], owner: 'sess-A'))->save($this->dir);

        $lines = [];
        $blocked = PilgrimageLock::blocks($this->dir, 'judge', function (string $l) use (&$lines): void {
            $lines[] = $l;
        });

        $this->assertTrue($blocked, 'the session that started the walk is locked');
        $joined = implode("\n", $lines);
        $this->assertStringContainsString('feature-request', $joined, 'the redirect lists the unscoped action');
        $this->assertStringContainsString('abandon', $joined, 'the redirect lists the clean exit');
        $this->assertStringContainsString('DECLINE', $joined, 'absolve/report are framed as decline, not shortcut');
    }

    public function test_a_different_session_is_never_blocked(): void
    {
        (new PilgrimageState(scope: ['/a.php'], owner: 'sess-A'))->save($this->dir);
        putenv('CLAUDE_CODE_SESSION_ID=sess-OTHER');

        $blocked = PilgrimageLock::blocks($this->dir, 'judge', static fn (string $l) => null);

        $this->assertFalse($blocked, 'a human / other session runs judge freely');
    }

    public function test_there_is_no_env_bypass(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');
        (new PilgrimageState(scope: ['/a.php'], owner: 'sess-A'))->save($this->dir);
        putenv('COMMANDMENTS_PILGRIMAGE_BYPASS=1');

        $blocked = PilgrimageLock::blocks($this->dir, 'judge', static fn (string $l) => null);

        putenv('COMMANDMENTS_PILGRIMAGE_BYPASS');

        $this->assertTrue($blocked, 'the retired env must NOT unlock the walk');
    }

    public function test_is_complete_rejects_a_forged_flag(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');
        // complete:true but the cursor never moved — a hand-written escape attempt.
        (new PilgrimageState(doctrine: 0, complete: true, owner: 'sess-A'))->save($this->dir);

        $this->assertFalse($this->runner()->isComplete(), 'completeness is recomputed from the cursor');
    }

    public function test_is_complete_accepts_a_genuinely_walked_pilgrimage(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');
        $total = $this->runner()->totalDoctrines();
        (new PilgrimageState(doctrine: $total, complete: true, owner: 'sess-A'))->save($this->dir);

        $this->assertTrue($this->runner()->isComplete());
    }

    public function test_is_complete_is_owner_scoped(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=sess-OTHER');
        $total = $this->runner()->totalDoctrines();
        (new PilgrimageState(doctrine: $total, complete: true, owner: 'sess-A'))->save($this->dir);

        $this->assertFalse($this->runner()->isComplete(), 'a foreign-session completion must not relax the gate');
    }
}
