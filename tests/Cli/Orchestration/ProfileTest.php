<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Profile;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A way of working, kept in git. Everything the runtime holds dies with the session — a profile is the
 * half that does not, which is what lets a second project start from what the first one learned.
 */
final class ProfileTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-profile-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function write(string $name, string $file, string $body): void
    {
        $path = $this->root . '/.commandments/orchestrator/profiles/' . $name . '/' . $file;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);
    }

    private function profiles(): Profiles
    {
        return Profiles::of(new Workspace($this->root, 'sess-1'));
    }

    public function test_a_profile_is_found_by_name(): void
    {
        $this->write('editor', 'profile.md', 'the editor migration');

        $this->assertSame('editor', $this->profiles()->named('editor')->unwrap()->name);
        $this->assertTrue($this->profiles()->named('nothing')->isNone());
    }

    /**
     * A document nobody wrote is absent rather than empty — a project may have nothing to say about
     * restrictions yet, and that is a fact about the profile rather than an error.
     */
    public function test_an_unwritten_document_is_absent(): void
    {
        $this->write('editor', 'traps.md', 'setsid does not exist on macOS');

        $profile = $this->profiles()->named('editor')->unwrap();

        $this->assertStringContainsString('setsid', $profile->document('traps')->unwrap());
        $this->assertTrue($profile->document('restrictions')->isNone());
    }

    /**
     * The one machine-read line in a role's prose. It is what lets `writtenBy` survive a restart: the
     * profile says which TYPE a role is, where a binding to an agent id dies with the agent.
     */
    public function test_a_role_declares_the_type_it_spawns_as(): void
    {
        $this->write('editor', 'roles/integrator.md', "# integrator\n\ntype: merger\n\nthe sole writer.");

        $profile = $this->profiles()->named('editor')->unwrap();

        $this->assertSame(['integrator'], $profile->roles());
        $this->assertSame('merger', $profile->typeOf('integrator')->unwrap());
    }

    public function test_a_role_without_a_type_says_so(): void
    {
        $this->write('editor', 'roles/walker.md', "# walker\n\nuses the product.");

        $this->assertTrue($this->profiles()->named('editor')->unwrap()->typeOf('walker')->isNone());
    }

    /**
     * The profile is durable and the instance is not: a restart correctly loses what was bound to a
     * process and keeps what was written down.
     */
    public function test_the_instance_is_the_session_half(): void
    {
        $workspace = new Workspace($this->root, 'sess-1');
        $instance = Instance::inSession($workspace);

        $this->assertFalse($instance->isRunning());

        $instance->start('editor', '21:41');

        $this->assertSame('editor', Instance::inSession($workspace)->profile()->unwrap());
        $this->assertTrue(
            Instance::inSession(new Workspace($this->root, 'sess-2'))->profile()->isNone(),
            'another session inherits no instance — the profile is shared, the running of it is not',
        );
    }

    public function test_every_document_a_profile_asks_for_has_a_purpose(): void
    {
        foreach (Profile::DOCUMENTS as $name => $about) {
            $this->assertNotSame('', $about, "{$name}.md must say what question it answers");
        }
    }
}
