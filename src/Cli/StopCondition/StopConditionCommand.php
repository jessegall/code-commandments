<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\StopCondition;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Hooks\StopHookCap;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments stop-condition "<condition>"` — the agent's handle on the stop gate ({@see StopConditionGate}). The
 * user says "keep going until the suite is green"; the agent records that here and the Stop hook
 * ({@see \JesseGall\CodeCommandments\Hooks\Handlers\StopConditionReminder}) then blocks every stop, telling
 * the agent to verify the condition before it may finish. Unlike the plan nudge this needs no plan:
 * a condition can be set at any moment, in any session.
 * A bare condition (or `add`) sets one, `list` shows what stands, `met <n>` strikes
 * one off as satisfied, `stuck` lets ONE stop through when the agent genuinely can't meet it, and
 * `clear` drops the gate entirely. `pause`/`resume` are THE USER's own handle — they set the whole
 * gate aside (conditions intact, nothing holding, no nudges) while they do something else in between,
 * and put it back when they're ready.
 */
final class StopConditionCommand implements Command
{
    /**
     * How many times a `stuck` claim is put back to the agent before the gate acts on it. Two: the first
     * asks whether it is a blocker at all, the second asks it to be certain nothing on the list can move
     * alone. It is friction on purpose — the claim ends a session, and #419/#420 were both made in the
     * moment an agent wanted the turn to be over. A third round would only teach agents to type it thrice.
     */
    private const int CHALLENGES = 2;

    /**
     * How many unaccounted-for conditions a refusal spells out before falling back to a count. Enough to
     * make the point concrete — the agent should SEE work it forgot it had — without reprinting a list of
     * 170 back at it.
     */
    private const int EXAMPLES = 3;

    /**
     * The ids in a `--blocked=2,5,9` list. Anything that is not a number is ignored rather than rejected:
     * a malformed id simply fails to account for its condition, which the coverage check then says plainly.
     *
     * @param  list<string>  $list
     * @return list<int>
     */
    private function ids(array $list): array
    {
        $ids = [];

        foreach ($list as $id) {
            if (trim($id) !== '') {
                $ids[] = (int) trim($id);
            }
        }

        return $ids;
    }

    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        // `until` stays because it is muscle memory and it is wired into hooks and skills that may
        // still name it — a rename that breaks a user's stop gate is a rename that costs more than
        // the clearer word is worth.
        return ['stop-condition', 'until'];
    }

    public function help(): Help
    {
        return Help::of("The user's STOP GATE — record what must hold before you may stop, and every stop is held until you have VERIFIED it. Needs no plan and no config.")
            ->form('stop-condition "<condition>"', 'set a condition (the form the user speaks; `add`/`set` do the same)')
            ->form('stop-condition list', 'what stands right now (the default), and what is paused')
            ->form('stop-condition met <n>', 'strike condition <n> off as VERIFIED — the gate lifts when none remain')
            ->form('stop-condition blocked <id> --reason="<what only the user can give>"', 'record that ONE condition is waiting on the user, and why — the reason is kept against that condition')
            ->form('stop-condition stuck', 'release ONE stop, once EVERY standing condition carries a reason. The claim is CHALLENGED twice before it is acted on')
            ->option('--reason=TEXT', 'what a blocked condition needs from the user — a reason that would fit any condition is not a reason for this one')
            ->option('--blocked=IDS', 'on `stuck`, a bulk `blocked` for these ids with the same --reason. Anything you leave out is work you still owe')
            ->form('stop-condition defer <n>', "take ONE condition out of the hold, keeping it and its words in the record — the project's backlog rather than what this session promised")
            ->form('stop-condition pull <n>', 'put a deferred condition back in the hold')
            ->form('stop-condition deferred', 'what is kept but no longer holding')
            ->form('stop-condition pause', "THE USER's switch — set the whole gate aside, conditions kept verbatim")
            ->form('stop-condition resume', 'put the paused gate back in force')
            ->form('stop-condition clear', "drop the gate entirely — the user's call, never an escape hatch")
            ->note('`stuck` is a claim about the WHOLE list, never about the item in hand: being blocked on one '
                . 'condition while others stand is not being stuck, it is having one blocked item and the rest '
                . 'still to work. So it is not asserted, it is COUNTED — you mark each condition blocked as you '
                . 'meet it, in whatever order you work them (`stop-condition blocked <id> --reason="…"`), and `stuck` is '
                . 'released only once every standing condition carries its own reason, and survives two '
                . 'challenges (running low on context, a big or mechanical change and "this needs its own change" '
                . 'are the WORK, not a blocker). A held stop DROPS every block, so the claim is always made afresh '
                . 'about the list as it stands then. Loop-safe — a run of held stops with no progress releases '
                . 'the gate (kept under the harness\'s own stop-hook cap, so the gate sets itself aside with '
                . 'every condition intact rather than being overridden), and meeting a condition resets that '
                . 'count. An ACTIVE PLAN takes precedence: the gate '
                . 'stays silent while the plan nudge owns the stop, then takes over at `plan done`.');
    }

    /**
     * Take one condition out of the hold. A gate is for what THIS SESSION promised; a project accumulates
     * findings and deferred mechanisms worth recording that are not that, and a gate holding every stop on
     * all of them cannot be satisfied by doing the work — 118 standing conditions, where striking nine
     * verified ones changed nothing. A gate like that is one an agent learns to route around, which is
     * worse for everybody than one admitting the distinction.
     */
    private function defer(StopConditionGate $gate, int $id): int
    {
        if (! $gate->park($id)) {
            return $this->tell("No condition {$id} — `commandments stop-condition list` shows what stands.");
        }

        return $this->tell(
            "○ Deferred {$id}. It is still in the record and no longer holds a stop.",
            "  `commandments stop-condition pull {$id}` puts it back when it becomes this session's work.",
        );
    }

    private function pull(StopConditionGate $gate, int $id): int
    {
        if (! $gate->pull($id)) {
            return $this->tell("No condition {$id}.");
        }

        return $this->tell("● Condition {$id} is holding again.");
    }

    private function listDeferred(StopConditionGate $gate): int
    {
        $deferred = $gate->parked();

        if ($deferred === []) {
            return $this->tell('Nothing deferred.');
        }

        $lines = [];

        foreach ($deferred as $id => $text) {
            $lines[] = sprintf('  %2d  %s', $id, $text);
        }

        return $this->tell(...$lines);
    }

    private function tell(string ...$lines): int
    {
        fwrite(STDOUT, implode("\n", $lines) . "\n");

        return 0;
    }

    public function run(Input $input): int
    {
        $gate = StopConditionGate::inSession(Workspace::at($this->io->projectRoot()));

        return match ($input->firstArgument()->unwrapOr('list')) {
            'add', 'set' => $this->add($gate, $this->text($input, from: 1)),
            'list', 'show', 'status' => $this->list($gate),
            'met', 'done', 'satisfied' => $this->met($gate, $input->argument(1)->mapOr(0, intval(...))),
            'blocked' => $this->blocked(
                $gate,
                $this->ids($input->listArgument(1)),
                $input->option('reason')->unwrapOr(''),
            ),
            'stuck' => $this->stuck(
                $gate,
                $this->ids($input->list('blocked')),
                $input->option('reason')->unwrapOr(''),
            ),
            'defer' => $this->defer($gate, $input->argument(1)->mapOr(0, intval(...))),
            'pull' => $this->pull($gate, $input->argument(1)->mapOr(0, intval(...))),
            'deferred' => $this->listDeferred($gate),
            'pause', 'hold' => $this->pause($gate),
            'resume', 'unpause', 'continue' => $this->resume($gate),
            'clear', 'cancel', 'drop' => $this->clear($gate),
            default => $this->add($gate, $this->text($input, from: 0)), // `stop-condition "<condition>"` — the
            // form the user actually speaks; no subcommand needed to set one.
        };
    }

    private function add(StopConditionGate $gate, string $condition): int
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
            . "  `commandments stop-condition resume`. Keep it on your to-do list (TodoWrite) so it is not lost.\n");

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
            . "  run `commandments stop-condition met {$number}`. If you are truly blocked and need the user, run\n"
            . "  `commandments stop-condition stuck` (that keeps the condition in force).\n");

        return 0;
    }

    private function list(StopConditionGate $gate): int
    {
        if ($gate->isOpen()) {
            fwrite(STDOUT, "● You may not stop until these hold:\n");
            $this->conditions($gate->all());

            if ($gate->blocked() !== []) {
                fwrite(STDOUT, "  Waiting on the user:\n");
                $this->reasons($gate);
            }

            $this->silences($gate);
        } else {
            fwrite(STDOUT, "○ No stop conditions in force.\n");
        }

        if ($gate->isPaused()) {
            fwrite(STDOUT, "❙❙ Paused (set aside, holding nothing — `commandments stop-condition resume` puts them back):\n");
            $this->conditions($gate->pausedConditions());
        }

        return 0;
    }

    /**
     * Say so when the gate is standing but holding nothing.
     *
     * A gate that cannot hold looks exactly like a gate that is not there — the user sets conditions,
     * the session runs to the end, and nothing is ever said. Two things quiet it, and both are
     * knowable here: a plan owns the stop while it runs, and a session that has done real work
     * without one single held stop is being overruled somewhere above us.
     */
    private function silences(StopConditionGate $gate): void
    {
        if (PlanMarker::inSession(Workspace::at($this->io->projectRoot()))->isActive()) {
            fwrite(STDOUT, "  ⚠ A plan is active, so these hold NOTHING yet — the plan owns the stop.\n"
                . "    They take over at `commandments plan done`.\n");

            return;
        }

        if ($gate->heldStops() === 0 && $gate->workDone()) {
            fwrite(STDOUT, "  ⚠ Work has been done and not ONE stop was held, which cannot happen while a gate\n"
                . "    stands. The Stop hook is not reaching this gate — check that `.claude/settings.json`\n"
                . "    still wires it, and that " . StopHookCap::VARIABLE . " is not set below the number of\n"
                . "    holds this gate needs (the harness overrides a hook that blocks past its cap).\n");
        }
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

    private function met(StopConditionGate $gate, int $number): int
    {
        $condition = $gate->met($number);

        if ($condition === null) {
            fwrite(STDERR, $gate->isPaused() && ! $gate->isOpen()
                ? "✗ The stop gate is PAUSED — nothing is holding you, and a paused condition is not struck off\n"
                    . "  until the user runs `commandments stop-condition resume`.\n"
                : "✗ No condition {$number} — run `commandments stop-condition list` to see what stands.\n");

            return 2;
        }

        $remaining = $gate->all();

        fwrite(STDOUT, "✓ Condition met: {$condition}\n" . ($remaining === []
            ? "  The stop gate is lifted — nothing else is holding you.\n"
            : '  ' . count($remaining) . " condition(s) still standing; run `commandments stop-condition list` to see them.\n")
            . "  NOW update the to-do list the user can SEE (TodoWrite): mark this item completed. Striking a\n"
            . "  condition off here does not touch that list — it goes stale the moment you skip this, and a\n"
            . "  stale list is the user watching work they cannot check.\n");

        return 0;
    }

    /**
     * Mark ONE condition as waiting on the user, and say why. This is where a `stuck` claim is actually
     * built: a condition at a time, in whatever order the agent meets them, each with its own reason
     * recorded against it ({@see Condition}). The agent that has to write a reason for a specific
     * condition finds out, in the writing, whether it has one.
     *
     * @param  list<int>  $ids
     */
    private function blocked(StopConditionGate $gate, array $ids, string $reason): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, "No stop conditions in force — nothing to mark blocked.\n");

            return 0;
        }

        if ($ids === [] || trim($reason) === '') {
            return HelpScreen::usage($this, 'name the condition and why it cannot move without the user: '
                . '`stop-condition blocked <id> --reason="<what you need from them>"`. A reason that would fit any '
                . 'condition is not a reason for THIS one.');
        }

        $marked = [];

        foreach ($ids as $id) {
            if ($gate->markBlocked($id, $reason)) {
                $marked[] = $id;
            }
        }

        if ($marked === []) {
            fwrite(STDERR, "✗ No standing condition with that id — run `commandments stop-condition list`.\n");

            return 2;
        }

        return $this->reportBlocked($gate, $marked);
    }

    /**
     * What is left after marking: the conditions still to work, or — when none are — that `stuck` is now
     * the honest move.
     *
     * @param  list<int>  $marked
     */
    private function reportBlocked(StopConditionGate $gate, array $marked): int
    {
        $left = $gate->unblocked();

        fwrite(STDOUT, '◼ Condition ' . implode(', ', $marked) . " marked as waiting on the user.\n"
            . "  NOW say so on the to-do list the user can SEE (TodoWrite), and do NOT tick it off: a blocked\n"
            . "  item is not a done item. Leave it open, and put what you need from them where they will read\n"
            . "  it — a list that shows a blocker as completed is worse than one that is merely stale.\n");

        if ($left === []) {
            fwrite(STDOUT,
                "  Every standing condition is now named as blocked, so `commandments stop-condition stuck` will put that\n"
                . "  claim to you and then release ONE stop. The conditions stay in force.\n");

            return 0;
        }

        fwrite(STDOUT, '  ' . count($left) . " condition(s) are still yours to work — one blocked item is not a\n"
            . "  blocked list. Go and finish these first; come back to the user with only the genuine blockers:\n");
        $this->conditions(array_slice($left, 0, self::EXAMPLES, preserve_keys: true));

        return 0;
    }

    /**
     * `stuck` — the claim that NOT ONE standing condition can move without the user. It is not asserted
     * here, it is COUNTED: every condition must already carry its own reason ({@see blocked}), so the
     * claim is the sum of what the agent has said about each one rather than a sentence about the item
     * in hand.
     *
     * This is the question #422 showed the tool was never asking. It asked "what are you blocked ON?",
     * a LOCAL question with an easy honest answer ("issue #224 needs a stack trace only Jesse has"), and
     * an agent that answered it truthfully was let through while 168 other conditions stood untouched.
     * The rule was always global; only the question was local. `--blocked=<ids> --reason="…"` stays as a
     * bulk form of `blocked`, for the agent that means the same reason for all of them.
     *
     * @param  list<int>  $ids  a bulk `blocked` for these conditions, when given
     */
    private function stuck(StopConditionGate $gate, array $ids, string $reason): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, "No stop conditions in force — nothing to be stuck on.\n");

            return 0;
        }

        foreach ($ids as $id) {
            $gate->markBlocked($id, $reason);
        }

        $unblocked = $gate->unblocked();

        if ($unblocked !== []) {
            return $this->notEveryCondition($gate, $unblocked);
        }

        $round = $gate->advanceClaim();

        if ($round <= self::CHALLENGES) {
            return $this->challenge($gate, $round);
        }

        $gate->markStuck();
        $gate->dropClaim();

        fwrite(STDOUT,
            "◼ Stop gate released for ONE stop. You have claimed that ALL " . count($gate->all()) . " standing\n"
            . "  condition(s) need the user — hand back now and put that claim to them in full, condition by\n"
            . "  condition. Everything stays in force; the gate holds again the moment you continue, and the\n"
            . "  blocks are dropped with it, so the claim is made afresh next time. The user is being shown\n"
            . "  what you stood by:\n");
        $this->reasons($gate);

        return 0;
    }

    /**
     * Refuse a claim that does not cover the list, and say so in the terms the agent got wrong: not "your
     * reason is poor" but "you have spoken for N of M — the other K are yours to work".
     *
     * @param  array<int, string>  $unblocked  the standing conditions with no reason against them
     */
    private function notEveryCondition(StopConditionGate $gate, array $unblocked): int
    {
        $standing = count($gate->all());
        $left = count($unblocked);

        fwrite(STDERR,
            "✗ `stuck` refused — it is a claim about the WHOLE list, and " . ($standing - $left) . " of {$standing}\n"
            . "  condition(s) is not the whole list.\n"
            . "  `stuck` does not mean \"the thing in front of me needs the user\". It means \"NOT ONE of these\n"
            . "  {$standing} can move without the user\". Being blocked on the item in hand while others stand is\n"
            . "  not being stuck — it is having one blocked item and {$left} still to work.\n"
            . "  You have said nothing about these {$left}:\n");

        $this->conditions(array_slice($unblocked, 0, self::EXAMPLES, preserve_keys: true));

        fwrite(STDERR, ($left > self::EXAMPLES ? '  …and ' . ($left - self::EXAMPLES) . " more (`stop-condition list`).\n" : '')
            . "  WORK them. For any that genuinely needs the user, say so against that condition — one at a\n"
            . "  time, with what you need from them:\n"
            . "  `commandments stop-condition blocked <id> --reason=\"<what only the user can give>\"`\n"
            . "  When every standing condition carries a reason, `stop-condition stuck` releases a stop.\n");

        return 2;
    }

    /**
     * Put the claim back to the agent instead of acting on it. The command cannot know whether a blocker is
     * real, but it knows the shapes that are NOT blockers — and it knows the reasons the agent gave, which it
     * has to read again to answer. Two rounds: the first asks whether anything on the list can still move
     * without the user, the second asks it to be certain. The claim is honoured on the round after, so a
     * genuinely blocked agent is delayed, never trapped.
     */
    private function challenge(StopConditionGate $gate, int $round): int
    {
        $standing = count($gate->all());

        fwrite(STDOUT, $round === 1
            ? "⚠ Before that stop is released — you are claiming all {$standing} of these need the user.\n"
                . "  Read your own reasons back against the LIST, not against the item in your hand. These are\n"
                . "  NOT blockers, they are the work itself: running low on context, too many call sites, a big\n"
                . "  or mechanical change, \"this needs its own change\", \"I'd be guessing on some of them\",\n"
                . "  being tired of the task. Blocked means a DECISION or INFORMATION only the user can give,\n"
                . "  that no file, test or command can tell you:\n"
            : "⚠ Once more, and be certain — about the OTHERS.\n"
                . "  The question is not whether the item you are on is blocked. It is: why can NONE of the\n"
                . "  other " . max(0, $standing - 1) . " move? Take them one at a time and read what you wrote for each. If even\n"
                . "  one could be advanced, verified or knocked off on your own, do that instead — you can come\n"
                . "  back to this.\n");

        $this->reasons($gate);

        fwrite(STDOUT, $round === 1
            ? "  If ANY one of these can still move without the user, close it FIRST — going back to work voids\n"
                . "  this claim, which is exactly right. If not one of them can, run the same command again to\n"
                . "  stand by it.\n"
            : "  Still certain that ALL {$standing} are waiting on the user? Run the same command once more and\n"
                . "  the stop is yours — and the user will be shown the reasons you gave, so say things you\n"
                . "  would defend to their face.\n");

        return 2;
    }

    /**
     * The blocked conditions as the agent and the user read them: the id, the condition, and what that
     * one is waiting on.
     */
    private function reasons(StopConditionGate $gate): void
    {
        $conditions = $gate->all();

        foreach ($gate->blocked() as $id => $reason) {
            fwrite(STDOUT, "  {$id}. " . ($conditions[$id] ?? '') . "\n     ↳ needs the user: {$reason}\n");
        }
    }

    /**
     * The user's pause — the whole gate is set aside so nothing holds a stop while they do something
     * else, and every condition waits, intact, for `stop-condition resume`.
     */
    private function pause(StopConditionGate $gate): int
    {
        if (! $gate->isOpen()) {
            fwrite(STDOUT, $gate->isPaused()
                ? "○ The stop gate is already paused — run `commandments stop-condition resume` to bring it back.\n"
                : "○ No stop conditions in force — nothing to pause.\n");

            return 0;
        }

        $paused = count($gate->all());
        $gate->pause();

        fwrite(STDOUT, "❙❙ Stop gate paused — {$paused} condition(s) set aside, nothing is holding you now.\n"
            . "  They are kept as-is; run `commandments stop-condition resume` to put them back in force.\n");

        return 0;
    }

    private function resume(StopConditionGate $gate): int
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

    private function clear(StopConditionGate $gate): int
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
     * The condition text from the arguments at $from onward — so both `stop-condition "a b c"` and an unquoted
     * `stop-condition a b c` read the same.
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
