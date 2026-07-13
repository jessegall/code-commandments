<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class WorkspaceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-workspace-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID');
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_session_key_is_a_five_char_hash_of_the_session_id(): void
    {
        $ws = new Workspace($this->dir, 'abc-123');

        $this->assertSame(substr(sha1('abc-123'), 0, 5), $ws->sessionKey());
        $this->assertSame(5, strlen($ws->sessionKey()));
    }

    public function test_without_a_session_id_the_default_folder_is_used(): void
    {
        $this->assertSame('default', new Workspace($this->dir)->sessionKey());
    }

    public function test_at_resolves_explicit_id_over_env_over_default(): void
    {
        putenv('CLAUDE_CODE_SESSION_ID=from-env');

        $this->assertSame(substr(sha1('explicit'), 0, 5), Workspace::at($this->dir, 'explicit')->sessionKey(), 'explicit wins');
        $this->assertSame(substr(sha1('from-env'), 0, 5), Workspace::at($this->dir)->sessionKey(), 'env fills in');

        putenv('CLAUDE_CODE_SESSION_ID');
        $this->assertSame('default', Workspace::at($this->dir)->sessionKey(), 'default without either');
    }

    public function test_different_session_ids_never_share_a_path(): void
    {
        $a = Workspace::at($this->dir, 'session-a')->path('.cardinal-remind-count');
        $b = Workspace::at($this->dir, 'session-b')->path('.cardinal-remind-count');

        $this->assertNotSame($a, $b);
    }

    public function test_path_shapes(): void
    {
        $ws = new Workspace($this->dir, 'abc');
        $key = $ws->sessionKey();

        $this->assertSame($this->dir . '/.commandments', $ws->dir());
        $this->assertSame($this->dir . "/.commandments/sessions/{$key}", $ws->sessionDir());
        $this->assertSame($this->dir . "/.commandments/sessions/{$key}/sins.md", $ws->path('sins.md'));
        $this->assertSame($this->dir . '/.commandments/config.php', $ws->shared('config.php'), 'durable tier stays flat');
        $this->assertSame(".commandments/sessions/{$key}/sins.md", $ws->relative('sins.md'));
    }

    public function test_prune_sweeps_stale_siblings_but_spares_current_fresh_and_durable(): void
    {
        $ws = new Workspace($this->dir, 'current');

        mkdir($ws->sessionDir(), 0777, true);
        file_put_contents($ws->path('.plan-active'), "mine\n");
        touch($ws->sessionDir(), time() - 30 * 86400); // even a stale CURRENT dir survives

        $stale = $ws->dir() . '/sessions/aaaaa';
        mkdir($stale, 0777, true);
        file_put_contents($stale . '/sins.md', "old\n");
        touch($stale, time() - 30 * 86400);

        $fresh = $ws->dir() . '/sessions/bbbbb';
        mkdir($fresh, 0777, true);

        file_put_contents($ws->shared('config.php'), "<?php\n");

        $ws->prune();

        $this->assertDirectoryDoesNotExist($stale);
        $this->assertDirectoryExists($fresh);
        $this->assertFileExists($ws->path('.plan-active'));
        $this->assertFileExists($ws->shared('config.php'));
    }
}
