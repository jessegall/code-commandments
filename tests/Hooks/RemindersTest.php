<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Holes;
use JesseGall\CodeCommandments\Hooks\Reminders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where a hook's words come from — a file this package ships, read as the thing the agent actually
 * hears. Less than the file holds: the title names it in a listing and the comment tells whoever has it
 * open what the holes are, and neither is addressed to the agent being nudged.
 */
final class RemindersTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-reminders-' . uniqid('', true);
        mkdir($this->root . '/templates/reminders', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function shipping(string $name, string $body): Reminders
    {
        file_put_contents($this->root . '/templates/reminders/' . $name . '.md', $body);

        return new Reminders($this->root);
    }

    public function test_the_holes_are_filled(): void
    {
        $said = $this->shipping('nudge', "# nudge\n\nClose it: {work}")->say('nudge', Holes::none()->with('work', 'the rename'));

        $this->assertSame('Close it: the rename', $said->unwrap());
    }

    /**
     * The parts written for whoever EDITS the file are not spoken. Saying them puts `# journal-quiet` and
     * a paragraph of instructions in front of an agent that asked for one line.
     */
    public function test_a_reminders_own_scaffolding_is_not_spoken(): void
    {
        $said = $this->shipping('nudge', "# nudge\n\n<!-- holes: {work} -->\n\nClose it: {work}")
            ->say('nudge', Holes::none()->with('work', 'the rename'));

        $this->assertSame('Close it: the rename', $said->unwrap());
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
     * A name nothing ships is a typo, and the caller is told so by getting nothing — every hook treats
     * that as "say nothing and do not hold the turn".
     */
    public function test_a_name_nothing_ships_says_nothing(): void
    {
        $this->assertTrue(new Reminders($this->root)->say('no-such-nudge', Holes::none())->isNone());
    }

    /**
     * Every reminder a hook asks for by name must be a file this package ships, or the hook goes silent
     * in every session.
     */
    #[DataProvider('named')]
    public function test_every_reminder_a_hook_names_is_shipped(string $name): void
    {
        $this->assertTrue(
            Reminders::shipped()->say($name, Holes::none())->isSome(),
            "no shipped template for `{$name}`",
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function named(): array
    {
        return [
            ['journal-quiet'],
            ['journal-enforced'],
            ['journal-open'],
            ['journal-standing'],
            ['journal-unheard'],
            ['journal-habit'],
        ];
    }
}
