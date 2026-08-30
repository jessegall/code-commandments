<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Holes;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Reminders;
use JesseGall\CodeCommandments\Cli\Orchestration\Templates;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Where a hook's words come from. The two sources answer in one order and the order is the whole rule: a
 * profile that HAS a file for a reminder owns its wording outright, including the right to have emptied
 * it; anywhere else — no profile, or a profile with no file by that name — the package answers, because
 * neither gap is a decision to say nothing.
 */
final class RemindersTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-reminders-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function workspace(): Workspace
    {
        return new Workspace($this->root, 'sess-1');
    }

    private function reminders(): Reminders
    {
        return Reminders::inSession($this->workspace());
    }

    private function profileFolder(): string
    {
        return $this->root . '/.commandments/orchestrator/profiles/dogfood';
    }

    /**
     * A profile in force, holding whatever $reminders it is given. An EMPTY body is the off switch and an
     * absent file is no opinion at all — telling those two apart is the case the whole design turns on.
     *
     * @param  array<string, string>  $reminders
     */
    private function orchestratingWith(array $reminders): void
    {
        mkdir($this->profileFolder() . '/reminders', 0777, true);

        foreach ($reminders as $name => $body) {
            file_put_contents($this->profileFolder() . '/reminders/' . $name . '.md', $body);
        }

        Instance::inSession($this->workspace())->start('dogfood', '10:00');
    }

    public function test_a_session_not_orchestrating_still_gets_the_shipped_words(): void
    {
        $said = $this->reminders()->say('journal-habit', Holes::none()->with('binary', 'bin/commandments'));

        $this->assertTrue($said->isSome(), 'a hook outside a build must still speak');
        $this->assertStringContainsString('bin/commandments journal remember', $said->unwrap());
    }

    public function test_the_profile_in_force_owns_the_wording(): void
    {
        $this->orchestratingWith(['journal-habit' => 'Say it in our own words: {binary}.']);

        $said = $this->reminders()->say('journal-habit', Holes::none()->with('binary', 'bin/commandments'));

        $this->assertSame('Say it in our own words: bin/commandments.', $said->unwrap());
    }

    /**
     * The off switch, and the only one — worth a test of its own because the tempting implementation, a
     * fallback whenever the profile does not answer, takes it away silently: the nudge comes back from
     * the package and the project has no way left to stop it.
     */
    public function test_an_emptied_reminder_under_a_profile_is_silenced(): void
    {
        $this->orchestratingWith(['journal-habit' => '']);

        $this->assertTrue($this->reminders()->say('journal-habit', Holes::none())->isNone());
    }

    /**
     * The case that made "absent means silence" untenable. Every profile in the world is older than the
     * next reminder we ship, and the live one had exactly one file in its folder — so reading a gap as a
     * decision would have muted every nudge nobody ever switched off.
     */
    public function test_a_profile_with_no_file_for_it_has_no_opinion_and_the_shipped_words_stand(): void
    {
        $this->orchestratingWith(['orchestrator' => 'ours']);

        $said = $this->reminders()->say('journal-habit', Holes::none()->with('binary', 'bin/commandments'));

        $this->assertStringContainsString('journal remember', $said->unwrap());
    }

    /**
     * A BRIEF is not advice. Deleting it would not stop a dispatch, it would send an agent out with an
     * empty prompt — so the words a caller cannot do without always resolve.
     */
    public function test_a_brief_resolves_even_where_a_nudge_would_be_silenced(): void
    {
        $this->orchestratingWith(['dispatch' => '']);

        $said = $this->reminders()->insist('dispatch', Holes::none()->with('procedure', 'review')->with('binary', 'bin/x'));

        $this->assertStringContainsString('subagent of the orchestrator', $said);
        $this->assertStringContainsString('bin/x build', $said);
    }

    /**
     * A hole nothing was given for SURVIVES. An empty space would claim the measurement was zero, where
     * `{count}` says plainly that nothing measured it.
     */
    public function test_an_unfilled_hole_survives_rather_than_blanking(): void
    {
        $this->assertSame('{count} left', Holes::none()->fill('{count} left'));
    }

    /**
     * Every reminder a hook asks for by name must be a file this package ships, or the fallback resolves
     * to nothing and the hook goes silent in every session that is not orchestrating.
     */
    public function test_every_reminder_a_hook_names_is_shipped(): void
    {
        $shipped = Templates::shipped()->all();

        foreach ($this->named() as $name) {
            $this->assertContains('reminders/' . $name, $shipped, "no shipped template for `{$name}`");
        }
    }

    /**
     * @return list<string>
     */
    private function named(): array
    {
        return [
            'orchestrator',
            'board-waiting',
            'routine',
            'journal-quiet',
            'journal-enforced',
            'journal-open',
            'journal-standing',
            'journal-unheard',
            'journal-habit',
        ];
    }
}
