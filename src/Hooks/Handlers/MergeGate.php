<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Hooks\Discipline;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\ShellCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Roles;
use JesseGall\CodeCommandments\OrchestrationProfile;
use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\PhpTypes\Option;

/**
 * Refuses a merge into the shared branch by any role but the one that owns it — the constraint that most
 * often holds only because somebody remembers it. It judges the ROLE and never the directory: a reviewer
 * steps into a lane to inspect it and the shell stays there, so a rule reading the working directory
 * would strip the one role that must merge of the ability to, exactly as it was about to.
 */
final class MergeGate extends Hook implements Discipline
{
    private const string MERGE = 'merge';

    public function summary(): string
    {
        return 'Refuses a merge into the declared shared branch by any role but the one that owns it.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreToolUse', 'Bash')];
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        $profile = Config::load($event->root)->orchestrationSettings();

        foreach ($profile->branch() as $branch) {
            return $this->judge($event, $profile, $branch);
        }

        return $this->pass(); // No branch declared, so no merge is this rule's business.
    }

    private function judge(HookEvent $event, OrchestrationProfile $profile, string $branch): int
    {
        if (! $this->isMergeInto($event->command(), $branch, $event->cwd())) {
            return $this->pass();
        }

        if ($profile->writer()->isNone()) {
            return $this->pass();
        }

        $actor = $this->whoIsActing($event);

        if ($actor->isNone()) {
            // Nobody said who this is. A rule that refuses an actor it cannot name would refuse the
            // writer too, on the day the writer has not been distinguished yet — so it says so instead.
            return $this->quietly($event, $this->cannotTell($event, $profile, $branch));
        }

        if ($profile->isWrittenBy($actor->unwrapOr(''))) {
            return $this->pass();
        }

        $writer = $profile->writer()->unwrapOr('');
        $who = $actor->unwrapOr('');

        return $this->block(<<<TEXT
            Code Commandments — only `{$writer}` merges into `{$branch}`, and you are {$who}.

            One writer is what keeps the branch a place work ARRIVES rather than a place several agents
            race. Hand the work over as a committed sha and let `{$writer}` take it: it merges the branch,
            runs the gates on the branch itself, and answers for what landed. A worker's own green is not
            the branch's.
            TEXT);
    }

    /**
     * WHO is doing this, if anybody can say. An explicit assignment wins — it is the only way an agent
     * already alive can hold a role, since a type is fixed at spawn and the agents worth a role are the
     * ones a respawn would ruin. Otherwise the agent's own type, when it happens to name a role.
     *
     * Absent means nobody has said. That is not the same as "not the writer", and treating it so is what
     * would refuse every merge in a build whose agents all share one type.
     *
     * @return Option<string>
     */
    private function whoIsActing(HookEvent $event): Option
    {
        $assigned = Roles::inSession($event->sessionWorkspace())->of($event->agentId());

        return $assigned->isSome() ? $assigned : Option::fromTruthy($event->agentType());
    }

    /**
     * Said, not refused, when the actor cannot be named — because silence here reads as approval, and a
     * rule quietly doing nothing is how the original hook outage hid.
     */
    private function cannotTell(HookEvent $event, OrchestrationProfile $profile, string $branch): string
    {
        $writer = $profile->writer()->unwrapOr('');
        $binary = Binary::in($event->root);

        return "Code Commandments — `{$branch}` is declared as written by `{$writer}`, but this session "
            . "carries no role, so the rule cannot tell you from the writer and is NOT enforcing. Spawn "
            . "the role under its own agent type, or point it at a live agent: "
            . "`{$binary} build assign {$writer} --to=<agent-id>`.";
    }

    /**
     * Is this command a merge whose destination is the shared branch — that is, a merge run while
     * STANDING on it? Asked of every command the shell would start and of the directory each one would
     * run in, because an agent whose session is pinned to the repository root reaches its lane with a
     * leading `cd` inside the command string. Reading the hook's own directory answered for the root,
     * which stands on the shared branch, and refused every lane that pulled the branch into itself —
     * the one direction this gate's own docblock says it must allow.
     */
    private function isMergeInto(string $command, string $branch, string $in): bool
    {
        foreach (ShellCommand::of($command)->invocations($in) as $invocation) {
            if ($invocation->isGit(self::MERGE) && $this->standingOn($branch, $invocation->in)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the checkout at $in on $branch right now? A merge lands where you are STANDING, so this is also
     * the direction check: standing on the protected branch is what separates `lane -> branch`, which is
     * the thing guarded, from `branch -> lane`, which every lane does before reporting and which writes
     * nothing to the protected branch at all.
     *
     * It must be asked of the worktree the merge is running IN, not of the hook's own process. Reading
     * the hook's directory made a lane pulling the shared branch into itself look like a merge into the
     * shared branch — and the refusal was correct-looking, which is worse than one that reads as broken.
     */
    private function standingOn(string $branch, string $in): bool
    {
        $head = @shell_exec('git -C ' . escapeshellarg($in) . ' rev-parse --abbrev-ref HEAD 2>/dev/null');

        return trim((string) $head) === $branch;
    }
}
