<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\OrchestrateCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Profile;
use JesseGall\CodeCommandments\Cli\Orchestration\Templates;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * A new profile arrives with EVERYTHING this package ships already in it — every document, role,
 * procedure and reminder. Presence is not activation and the two are deliberately different: a project
 * that has to go looking for the wording of a nudge before it can change it will keep the nudge it has,
 * while a trigger armed by default would spawn a process on somebody's machine the first time they
 * committed.
 */
final class NewProfileTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-new-profile-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        $this->orchestrate('new', 'housestyle');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function orchestrate(string ...$args): string
    {
        $out = fopen('php://memory', 'r+');

        new OrchestrateCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::of('orchestrate', $args));

        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function profile(): Profile
    {
        return new Profile('housestyle', $this->root . '/.commandments/orchestrator/profiles/housestyle');
    }

    /**
     * The one assertion that covers every kind at once, and keeps covering the next kind we ship: a
     * template listed by the package and missing from a fresh profile is a default nobody will discover.
     */
    public function test_every_shipped_template_lands_in_a_new_profile(): void
    {
        $templates = Templates::shipped();

        foreach ($templates->all() as $template) {
            $home = $templates->homeIn($this->profile(), $template);

            $this->assertFileExists($home, "`{$template}` was not written into the new profile");
            $this->assertSame($templates->read($template)->unwrap(), (string) file_get_contents($home));
        }
    }

    /**
     * Procedures were the kind nothing scaffolded, so a project met the concept only by reading the
     * source. Named on its own because "every template" would go on passing if the package stopped
     * shipping procedures altogether.
     */
    public function test_a_procedure_is_scaffolded_like_a_role(): void
    {
        $this->assertNotSame([], $this->profile()->procedures());
        $this->assertNotSame([], $this->profile()->roles());
        $this->assertNotSame([], $this->profile()->reminders());
    }

    /**
     * A shell script is not a document. It inherited `pathTo`, which gives every piece of a profile's
     * prose its `.md`, and was scaffolded to `lane.sh.md` — where the runner looking for `lane.sh` found
     * nothing and every lane came up bare.
     */
    public function test_the_setup_script_is_written_where_the_runner_looks_for_it(): void
    {
        $this->assertTrue($this->profile()->setupScript()->isSome());
        $this->assertFileDoesNotExist($this->profile()->pathTo(Profile::SETUP));
    }

    /**
     * PRESENT, and off. The file is there to be found and edited; nothing in it spawns anything until
     * somebody arms it.
     */
    public function test_the_switch_file_is_present_and_arms_nothing(): void
    {
        $this->assertFileExists($this->profile()->settingsFile());
        $this->assertSame([], $this->profile()->allSettings());
        $this->assertSame([], $this->profile()->boundTo('commit'));
    }

    /**
     * A default nobody is told about is one nobody edits, so the scaffold says what it wrote and how to
     * arm what it deliberately did not.
     */
    public function test_the_scaffold_says_that_nothing_is_armed(): void
    {
        $printed = $this->orchestrate('new', 'second');

        $this->assertStringContainsString('Nothing is armed', $printed);
        $this->assertStringContainsString('orchestrate on <trigger>', $printed);
        $this->assertStringContainsString('reminders/<name>.md', $printed);
    }
}
