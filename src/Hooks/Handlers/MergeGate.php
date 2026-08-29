<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\OrchestrationProfile;

/**
 * Refuses a merge into the shared branch by any role but the one that owns it — the constraint that most
 * often holds only because somebody remembers it. It judges the ROLE and never the directory: a reviewer
 * steps into a lane to inspect it and the shell stays there, so a rule reading the working directory
 * would strip the one role that must merge of the ability to, exactly as it was about to.
 */
final class MergeGate extends Hook
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

    /**
     * The merge it refuses is done by a worker, and a worker is a subagent.
     */
    protected function speaksToSubagents(): bool
    {
        return true;
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
        if (! $this->isMergeInto($event->command(), $branch)) {
            return $this->pass();
        }

        if ($profile->writer()->isNone() || $profile->isWrittenBy($event->agentType())) {
            return $this->pass();
        }

        $writer = $profile->writer()->unwrapOr('');
        $who = $event->agentType() === '' ? 'this session' : $event->agentType();

        return $this->block(<<<TEXT
            Code Commandments — only `{$writer}` merges into `{$branch}`, and you are {$who}.

            One writer is what keeps the branch a place work ARRIVES rather than a place several agents
            race. Hand the work over as a committed sha and let `{$writer}` take it: it merges the branch,
            runs the gates on the branch itself, and answers for what landed. A worker's own green is not
            the branch's.
            TEXT);
    }

    /**
     * Is this command a merge whose destination is the shared branch — that is, a merge run while standing
     * on it? A command carrying a heredoc or a quote may be WRITING about a merge, and text about a
     * command is not a command.
     */
    private function isMergeInto(string $command, string $branch): bool
    {
        foreach (['<<', "'", '"'] as $quoted) {
            if (! str_contains($command, $quoted)) {
                continue;
            }

            return false;
        }

        foreach (explode('&&', $command) as $part) {
            if ($this->isMerge(trim($part))) {
                return $this->standingOn($branch);
            }
        }

        return false;
    }

    private function isMerge(string $part): bool
    {
        $words = preg_split('/\s+/', $part) ?: [];

        return ($words[0] ?? '') === 'git' && in_array(self::MERGE, $words, true);
    }

    /**
     * Is the checkout on $branch right now? A merge lands where you are standing, so that is what decides
     * whether this merge is the one being guarded.
     */
    private function standingOn(string $branch): bool
    {
        return trim((string) @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')) === $branch;
    }
}
