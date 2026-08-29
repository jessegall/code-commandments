<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\OrchestrateCommand;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A role and a section are both TARGETS, and a target the profile does not have is a typo every time.
 * Accepting one silently scaffolds a sixth role out of a misspelling, or files an entry under a heading
 * the file does not have — in a folder whose whole purpose is to be the durable, reviewed record.
 */
final class UnknownTargetIsRefusedTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-target-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=target-test');

        $this->writeProfile('roles/integrator.md', "# integrator\n\ntype: merger\n\nthe sole writer.");
        $this->writeProfile('profile.md', 'the editor migration');

        Instance::inSession(Workspace::ofSession($this->root))->start('editor', '10:00');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function writeProfile(string $file, string $body): void
    {
        $path = $this->root . '/.commandments/orchestrator/profiles/editor/' . $file;

        is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);
    }

    private function orchestrate(string ...$argv): array
    {
        $out = fopen('php://memory', 'r+');

        $code = new OrchestrateCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'orchestrate', ...$argv]));

        rewind($out);

        return [$code, (string) stream_get_contents($out)];
    }

    private function roleFile(string $role): string
    {
        return $this->root . '/.commandments/orchestrator/profiles/editor/roles/' . $role . '.md';
    }

    public function test_a_misspelled_role_is_refused_and_no_file_is_created(): void
    {
        [$code, $said] = $this->orchestrate('assistant', 'nosuchrole', 'caught', 'a thing');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('nosuchrole', $said);
        $this->assertStringContainsString('integrator', $said, 'it names what the profile actually has');
        $this->assertFileDoesNotExist($this->roleFile('nosuchrole'), 'a typo must not scaffold a sixth role');
    }

    public function test_a_misspelled_section_is_refused_and_nothing_is_appended(): void
    {
        $before = (string) file_get_contents($this->roleFile('integrator'));

        [$code, $said] = $this->orchestrate('assistant', 'integrator', 'nosuchtarget', 'a thing');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('nosuchtarget', $said);
        $this->assertSame($before, (string) file_get_contents($this->roleFile('integrator')), 'the real file is untouched');
    }

    public function test_a_real_role_and_section_is_written(): void
    {
        [$code] = $this->orchestrate('assistant', 'integrator', 'caught', 'a duplicated claim');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('a duplicated claim', (string) file_get_contents($this->roleFile('integrator')));
    }

    public function test_a_document_a_profile_does_not_have_is_refused(): void
    {
        [$code, $said] = $this->orchestrate('profile', 'nosuchdocument', 'a thing');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('behaviour', $said, 'it names the documents a profile has');
    }
}
