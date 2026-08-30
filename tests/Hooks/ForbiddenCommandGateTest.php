<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Handlers\ForbiddenCommandGate;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * What the gate must refuse, and what it must let through. The second list is the one that earns its
 * keep: three separate false positives fired on PROSE ABOUT THE RULE — a probe that echoed the command,
 * and two commit messages describing the feature — because neither early version knew what a quoted
 * string was. A refusal that fires on writing about a command teaches people to rephrase until it stops.
 */
final class ForbiddenCommandGateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-forbid-' . uniqid('', true);
        $roles = $this->root . '/.commandments/orchestrator/profiles/p/roles';
        mkdir($roles, 0777, true);
        file_put_contents($roles . '/../settings.json', json_encode(['forbid' => ['git stash']]));

        // Put the profile in force through the API that owns the state file's format, and into the
        // workspace the GATE will resolve — the key comes from the environment, so a fixture that names
        // its own session folder, or hand-writes the file, tests something nothing reads.
        Instance::inSession(Workspace::ofSession($this->root))->start('p', 'now');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function refuses(string $command): bool
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => $command],
        ], new FakeGit($this->root));

        new ForbiddenCommandGate($io)->run([]);

        foreach ($io->emitted as $response) {
            if ($response->blockReason->isSome()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{string}>
     */
    public static function invocations(): array
    {
        $stash = 'git st' . 'ash';

        return [
            [$stash],
            ["cd /tmp && {$stash}"],
            ["echo x; {$stash}"],
            ["true | {$stash}"],
        ];
    }

    /**
     * PROSE, every one of which a shell would never run. Written so the test file itself does not carry
     * the literal command at a segment head, since this suite runs under the very gate it tests.
     *
     * @return list<array{string}>
     */
    public static function prose(): array
    {
        $stash = 'git st' . 'ash';

        return [
            ["echo \"prose; {$stash} named here\""],
            ["echo 'prose; {$stash} here'"],
            ["git commit -m \"refused after a cd && and then {$stash} named\""],
            ["echo \"mentions {$stash} plainly\""],
        ];
    }

    /**
     * @dataProvider invocations
     */
    public function test_it_refuses_a_command_that_runs_the_forbidden_one(string $command): void
    {
        $this->assertTrue($this->refuses($command), "[{$command}] runs a forbidden command and must be refused");
    }

    /**
     * @dataProvider prose
     */
    public function test_it_permits_a_command_that_only_mentions_one(string $command): void
    {
        $this->assertFalse($this->refuses($command), "[{$command}] only NAMES the command inside a quoted string; a shell would never run it");
    }
}
