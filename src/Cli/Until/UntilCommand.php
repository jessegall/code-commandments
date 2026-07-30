<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Until;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments until "<condition>"` — the agent's handle on the stop gate ({@see UntilGate}). The
 * user says "keep going until the suite is green"; the agent records that here and the Stop hook
 * ({@see \JesseGall\CodeCommandments\Hooks\Handlers\UntilReminder}) then blocks every stop, telling
 * the agent to verify the condition before it may finish. Unlike the plan nudge this needs no plan:
 * a condition can be set at any moment, in any session.
 * A bare condition (or `add`) sets one, `list` shows what stands, `met <n>` strikes
 * one off as satisfied, `stuck` lets ONE stop through when the agent genuinely can't meet it, and
 * `clear` drops the gate entirely. `pause`/`resume` are THE USER's own handle — they set the whole
 * gate aside (conditions intact, nothing holding, no nudges) while they do something else in between,
 * and put it back when they're ready.
 */
final class UntilCommand implements Command
{
    /**
     * How many times a `stuck` claim is put back to the agent before the gate acts on it. Two: the first
     * asks whether it is a blocker at all, the second asks it to be certain nothing on the list can move
     * alone. It is friction on purpose — the claim ends a session, and #419/#420 were both made in the
     * moment an agent wanted the turn to be over. A third round would only teach agents to type it thrice.
     */
    private const int CHALLENGES = 2;

    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['until'];
    }

    public function help(): Help
    {
        return Help::of("The user's STOP GATE — record what must hold before you may stop, and every stop is held until you have VERIFIED it. Needs no plan and no config.")
            ->form('until "<condition>"', 'set a condition (the form the user speaks; `add`/`set` do the same)')
            ->form('until list', 'what stands right now (the default), and what is paused')
            ->form('until met <n>', 'strike condition <n> off as VERIFIED — the gate lifts when none remain')
            ->form('until stuck --reason="<what you need FROM THE USER>"', 'release ONE stop when you are genuinely blocked, keeping every condition in force — the claim is CHALLENGED twice before it is acted on')
            ->option('--reason=TEXT', 'the decision or information only the user can give — required, and shown back to them when the claim stands')
            ->form('until pause', "THE USER's switch — set the whole gate aside, conditions kept verbatim")
            ->form('until resume', 'put the paused gate back in force')
            ->form('until clear', "drop the gate entirely — the user's call, never an escape hatch")
            ->note('`stuck` is a claim about the WHOLE list: drain every condition you can advance on your own '
                . 'before asking the user — and it is TESTED, not taken on trust: while several conditions stand, '
                . 'a `stuck` with no work done since the gate last sent you back is refused, and every `stuck` must '
                . 'name what it needs from the user and survive two challenges (running low on context, a big or '
                . 'mechanical change and "this needs its own change" are the WORK, not a blocker). Going back to '
                . 'work voids a half-answered claim. Loop-safe — 10 consecutive held stops with no progress release the gate, and meeting a condition resets that count. An ACTIVE PLAN takes precedence: the gate stays silent while the plan nudge owns the stop, then takes over at `plan done`.');
    }

    public function run(Input $input): int
    {
        $gate = UntilGate::inSession(Workspace::at($this->io->projectRoot()));

        return match ($input->firstArgument('list')) {
            'add', 'set' => $this->add($gate, $this->text($input, from: 1)),
            'list', 'show', 'status' => $this->list($gate),
            'met', 'done', 'satisfied' => $this->met($gate, (int) ($input->arguments()[1] ?? '0')),
            'stuck', 'blocked' => $this->stuck($gate, (string) $input->option('reason', '')),
            'pause', 'hold' => $this->pause($gate),
            'resume', 'unpause', 'continue' => $this->resume($gate),
            'clear', 'cancel', 'drop' => $this->clear($gate),
            default => $this->add($gate, $this->text($input, from: 0)), // `until "<condition>"` — the
            // form the user actually speaks; no subcommand needed to set one.
        };
    }

    private function add(UntilGate $gate, string $condition): int
    {
        if ($condition === '') {
            return $this->usage();
        }

        return $gate->isPaused()
            ? $this->announceParked($condition, $gate->add($condition))
            : $this->announce($condition, $gate->add($condition));
    }

    /**
     * The gate is set aside, so the condition is kept WITH the paused ones and holds nothing yet —
     * say so plainly, or the agent would read the ordinary announcement and think a stop is barred
     * when the user's pause says otherwise (#418).
     */
    private function announceParked(string $condition, int $number): int
    {
        fwrite(STDOUT,
            "❙❙ Condition {$number} recorded WITH THE PAUSED GATE: {$condition}\n"
            . "  The user paused the gate, so nothing is holding you — this waits, intact, until they run\n"
            . "  `commandments until resume`. Keep it on your to-do list (TodoWrite) so it is not lost.\n");

        return 0;
    }

    /**
     * Report a newly-set condition and the number that signs it off.
     */
    private function announce(string $condition, int $number): int
    {
        fwrite(STDOUT,
            "● Stop gate set (condition {$number}): {$condition}\n"
            . "  You may not stop until this holds. Add this condition to your to-do list (TodoWrite) as a\n"
            . "  pending item so the user can see what is holding you, and mark it done when you meet it.\n"
            . "  Every time you try to stop you will be sent back in to verify it — when it genuinely holds,\n"
            . "  run `commandments until met {$number}`. If you are truly blocked and need the user, run\n"
            . "  `commandments until stuck` (that keeps the condition in force).\n");

        return 0;
    }

    private function list(UntilGate $gate): int
    {
        if ($gate->isOpen()) {
            fwrite(STDOUT, "● You may not stop until these hold:\n");
            $this->conditions($gate->all());
        } else {
            fwrite(STDOUT, "○ No stop conditions in force.\n");
        }

        if ($gate->isPaused()) {
            fwrite(STDOUT, "❙❙ Paused (set aside, holding nothing — `commandments until resume` puts them back):\n");
            $this->conditions($gate->pausedConditions());
        }

        return 0;
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their STABLE id — printed as-is, since that id
     *                                          stays valid after another condition is struck off, so a
     *                                          batch of `met` calls read off one list can't miss
     */
    private function conditions(array $conditions): void
    {
        foreach ($conditions as $id => $condition) {
            fwrite(STDOUT, "  {$id}. {$condition}\n");
        }
    }

    private function met(UntilGate $gate, int $number): int
    {
        $condition = $gate->met($number);

        if ($condition === null) {
            fwrite(STDERR, $gate->isPaused() && ! $gate->isOpen()
                ? "✗ The stop gate is PAUSED — nothing is holding you, and a paused condition is not struck off\n"
                    . "  until the user runs `commandments until resume`.\n"
                : "✗ No condition {$number} — run `commandments until list` to see what stands.\n");

            return 2;
        }

        $remaining = $gate->all();

        fwrite(STDOUT, "✓ Condition met: {$condition}\n" . ($remaining === []
            ? "  The stop gate is lifted — nothing else is holding you.\n"
            : '  ' . count($remaining) . " condition(s) still standing; run `commandments until list` to see them.\n")
            . "  NOW update the to-do list the user can SEE (TodoWrite): mark this item completed. Striking a\n"
            . "  condition off here does not touch that list — it goes stale the moment you skip this, and a\n"
            . "  stale list is the user watching work they cannot check.\n");

        return 0;
    }

    private function stuck(UntilGate $gate, string $reason): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, "No stop conditions in force — nothing to be stuck on.\n");

            return 0;
        }

        if ($this->isUntested($gate)) {
            return $this->refuse($gate);
        }

        if ($reason === '') {
            return $this->reasonRequired();
        }

        $round = $gate->advanceClaim($reason);

        if ($round <= self::CHALLENGES) {
            return $this->challenge($gate, $reason, $round);
        }

        $gate->markStuck();
        $gate->dropClaim();

        fwrite(STDOUT,
            "◼ Stop gate paused for ONE stop — you are blocked, so hand back to the user and tell them exactly\n"
            . "  which condition you cannot meet and why. The condition stays in force: the gate holds again as\n"
            . "  soon as you continue. (Use `until met <n>` only when a condition actually holds.)\n"
            . "  The user is being shown the claim you stood by: \"{$reason}\"\n");

        $this->challengeTheRest($gate);

        return 0;
    }

    /**
     * `stuck` is a claim, and a claim with no reason cannot be examined at all — by the agent making it or
     * by the user reading it afterwards.
     */
    private function reasonRequired(): int
    {
        return HelpScreen::usage($this, 'a `stuck` must say what it is blocked ON: `until stuck '
            . '--reason="<the decision or information you need FROM THE USER>"`. Not what is left to do, and '
            . 'not how much of it — what you need a HUMAN for.');
    }

    /**
     * Put the claim back to the agent instead of acting on it. The command cannot know whether a blocker is
     * real, but it knows the shapes that are NOT blockers — and it knows the standing conditions, which the
     * agent has to look at again to answer. Two rounds: the first asks whether anything on the list can still
     * move without the user, the second asks it to be certain. The claim is honoured on the round after, so
     * a genuinely blocked agent is delayed, never trapped.
     */
    private function challenge(UntilGate $gate, string $reason, int $round): int
    {
        fwrite(STDOUT, $round === 1
            ? "⚠ Before that stop is released — are you REALLY blocked?\n"
                . "  You said: \"{$reason}\"\n"
                . "  These are NOT being blocked, they are the work itself: running low on context, too many\n"
                . "  call sites, a big or mechanical change, \"this needs its own change\", \"I'd be guessing on\n"
                . "  some of them\", being tired of the task. Being blocked means you need a DECISION or\n"
                . "  INFORMATION that only the user can give, and that no file, test or command can tell you.\n"
                . "  Look at what still stands:\n"
            : "⚠ Once more, and be certain.\n"
                . "  You said: \"{$reason}\"\n"
                . "  Are you 100% sure there is NOTHING on this list you can finish without the user? Not one\n"
                . "  item you could advance, verify, or knock off on your own first? If there is even one, do\n"
                . "  that instead — you can always come back to this.\n");

        $this->conditions($gate->all());

        fwrite(STDOUT, $round === 1
            ? "  If one of these can still move without the user, close it FIRST — going back to work voids\n"
                . "  this claim, which is exactly right. If you are certain none can, run the same command again\n"
                . "  to stand by it.\n"
            : "  Still certain? Run the same command once more and the stop is yours — and the user will be\n"
                . "  shown the reason you gave, so say something you would defend to their face.\n");

        return 2;
    }

    /**
     * Is this `stuck` an UNTESTED claim — several conditions standing and not one piece of work done since
     * the gate last sent the agent back ({@see UntilGate::workSinceHold})? Then "I cannot advance ANY of
     * these without the user" has been asserted, not tried, which is exactly how a gate holding a dozen
     * untouched conditions ended a session early (#419).
     *
     * Deliberately limited to a MULTI-condition gate. With one condition left there may genuinely be
     * nothing to do but ask, and refusing then would leave a blocked agent with no honest way out; with
     * several, the drain argument the hold message makes is self-evidently untested.
     */
    private function isUntested(UntilGate $gate): bool
    {
        return count($gate->all()) > 1 && $gate->workSinceHold() === 0;
    }

    /**
     * Refuse the claim and send the agent back to the list. The conditions are untouched and no stop is
     * released — the very next `stuck`, once something has actually been attempted, goes through.
     */
    private function refuse(UntilGate $gate): int
    {
        $conditions = $gate->all();

        fwrite(STDERR,
            "✗ `stuck` refused — nothing has been WORKED since the gate last sent you back.\n"
            . '  ' . count($conditions) . " conditions stand and no tool use has touched any of them, so \"none of these\n"
            . "  can move without the user\" is a claim you have not tested. Advance the ones you can on your own\n"
            . "  FIRST, then come back with everything else DONE and only the genuine blocker left.\n"
            . "  Reading a file, running a command, editing code all count as working the list — explaining does\n"
            . "  not. Once you have tried, `stuck` lets you through.\n");

        $this->conditions($conditions);

        return 2;
    }

    /**
     * `stuck` claims the WHOLE list is blocked, so when several conditions stand, name the others back
     * at the agent: one condition waiting on the user is not a reason to leave the ones it can still
     * advance untouched. The signal is honoured either way — this is a challenge, not a refusal.
     */
    private function challengeTheRest(UntilGate $gate): void
    {
        $conditions = $gate->all();

        if (count($conditions) < 2) {
            return;
        }

        fwrite(STDOUT,
            "\n  ⚠ " . count($conditions) . " conditions stand. `stuck` says you cannot advance ANY of them without\n"
            . "  the user. If one of these can still be worked on your own, do that FIRST and come back to the user\n"
            . "  with only the genuine blocker left:\n");

        $this->conditions($conditions);
    }

    /**
     * The user's pause — the whole gate is set aside so nothing holds a stop while they do something
     * else, and every condition waits, intact, for `until resume`.
     */
    private function pause(UntilGate $gate): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, $gate->isPaused()
                ? "○ The stop gate is already paused — run `commandments until resume` to bring it back.\n"
                : "○ No stop conditions in force — nothing to pause.\n");

            return 0;
        }

        $paused = count($gate->all());
        $gate->pause();

        fwrite(STDOUT, "❙❙ Stop gate paused — {$paused} condition(s) set aside, nothing is holding you now.\n"
            . "  They are kept as-is; run `commandments until resume` to put them back in force.\n");

        return 0;
    }

    private function resume(UntilGate $gate): int
    {
        if (! $gate->isPaused()) {
            fwrite(STDOUT, "○ The stop gate is not paused.\n");

            return 0;
        }

        $gate->resume();

        fwrite(STDOUT, "● Stop gate resumed — you may not stop until these hold:\n");
        $this->conditions($gate->all());

        return 0;
    }

    private function clear(UntilGate $gate): int
    {
        if (! $gate->isOpen() && ! $gate->isPaused()) {
            fwrite(STDOUT, "○ No stop conditions in force.\n");

            return 0;
        }

        $gate->clear(); // Paused conditions go too — `clear` means the gate is gone, not set aside.
        fwrite(STDOUT, "✓ Stop gate cleared — every condition dropped.\n");

        return 0;
    }

    /**
     * The condition text from the arguments at $from onward — so both `until "a b c"` and an unquoted
     * `until a b c` read the same.
     */
    private function text(Input $input, int $from): string
    {
        return trim(implode(' ', array_slice($input->arguments(), $from)));
    }

    private function usage(): int
    {
        return HelpScreen::usage($this, 'Name the condition you may not stop until it holds.');
    }
}
