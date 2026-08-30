<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * The ONE place a background agent is started. A trigger reports its moment and its subject; which agents
 * the profile bound to it, whether one is already reading, the hold on the board, the lane, the brief and
 * the process all happen here — because a mechanism that only its first caller implements is not a
 * mechanism, and every trigger written after the first was inert by construction.
 */
final readonly class Dispatcher
{
    public function __construct(
        private Workspace $workspace,
        private string $root,
    ) {}

    /**
     * Start whatever $trigger is bound to, against $subject, and say what happened. The lines come back
     * rather than being printed: the caller is a hook, and only it knows whether this moment is one the
     * user should see.
     *
     * @return list<string>
     */
    public function fire(string $trigger, string $subject): array
    {
        $said = [];

        foreach (Profiles::inForce($this->workspace) as $profile) {
            foreach ($profile->boundTo($trigger) as $duty) {
                foreach ($this->start($profile, $duty, $trigger, $subject) as $line) {
                    $said[] = $line;
                }
            }
        }

        return $said;
    }

    /**
     * One duty. The QUEUE is the lock, not a flag of our own: an agent is a conversation and not a pool,
     * so a second subject arriving while it reads waits for it rather than starting a rival in the same
     * lane. And the hold goes on the board, where the orchestrator already looks to see who has what.
     *
     * @return list<string>
     */
    private function start(Profile $profile, Duty $duty, string $trigger, string $subject): array
    {
        if ($profile->procedure($duty->procedure)->isNone()) {
            return ["Code Commandments — `{$profile->name}` binds `{$duty->procedure}` on {$trigger} but has not written it."];
        }

        $queue = Queue::forAgent($this->workspace, $duty->agent);

        if (! $queue->take($subject)) {
            return [sprintf(
                'Code Commandments — `%s` is still on %s, so %s is QUEUED (%d waiting). It starts on its own.',
                $duty->agent,
                $this->short($queue->running()),
                $this->short($subject),
                count($queue->waiting()),
            )];
        }

        Board::inSession($this->workspace)->claim($duty->procedure, $duty->agent, gmdate('H:i'));
        $this->spawn($profile, $duty, $subject, $queue);

        return [sprintf(
            "Code Commandments — `%s` is running `%s` against %s in its own lane, holding `%s` on the board. "
                . "Its words land in %s.\n"
                . 'SAY THIS TO THE USER, in your next reply, in one line. A hook cannot reach their terminal '
                . '— it can only reach you — and this one started a process on their machine that reads '
                . 'their code and writes files. Nobody should learn an agent appeared by finding it in a log.',
            $duty->agent,
            $duty->procedure,
            $this->short($subject),
            $duty->procedure,
            $this->workspace->relative($this->logFor($duty)),
        )];
    }

    /**
     * Start it and let go. Everything happens inside ONE detached shell — the lane first, then the agent
     * — so a lane that takes a minute to prepare does not hold the moment that triggered it.
     *
     * `< /dev/null` matters: a child inheriting a terminal's stdin waits on it, and its parent then waits
     * on the child. The prompt goes on stdin rather than in argv, because a brief carries a whole role
     * and procedure and argv has a length nobody discovers until a procedure grows past it. And the loop
     * is what drains the queue: `queue next` prints the following brief or prints NOTHING, and nothing is
     * the signal to stop — a loop that must parse a sentence to learn it is done eventually misreads it.
     */
    private function spawn(Profile $profile, Duty $duty, string $subject, Queue $queue): void
    {
        $binary = Binary::in($this->root);
        $lane = Checkout::homeFor($this->workspace, $this->root) . '/' . $duty->agent;
        $log = $this->workspace->path($this->logFor($duty));
        $prompt = $this->workspace->path('.' . $duty->agent . '.prompt');
        $resuming = $queue->hasConversation();
        $conversation = $queue->conversation();

        File::write($prompt, $this->brief($profile, $duty, $subject));

        $inner = sprintf(
            '%s lane open %s >> %s 2>&1; cd %s 2>/dev/null || cd %s; c=%s; '
                . 'while :; do claude --print $c < %s >> %s 2>&1; c=%s; '
                . '(cd %s && %s queue next %s) > %s 2>/dev/null; [ -s %s ] || break; done',
            escapeshellarg($binary),
            escapeshellarg($duty->agent),
            escapeshellarg($log),
            escapeshellarg($lane),
            escapeshellarg($this->root),
            $resuming ? '--resume ' . escapeshellarg($conversation) : '--session-id ' . escapeshellarg($conversation),
            escapeshellarg($prompt),
            escapeshellarg($log),
            '--resume ' . escapeshellarg($conversation),
            escapeshellarg($this->root),
            escapeshellarg($binary),
            escapeshellarg($duty->agent),
            escapeshellarg($prompt),
            escapeshellarg($prompt),
        );

        exec(sprintf('nohup sh -c %s < /dev/null >> %s 2>&1 &', escapeshellarg($inner), escapeshellarg($log)));
    }

    /**
     * WHO it is, WHAT to do, and the envelope every dispatched agent gets whatever started it — telling
     * the orchestrator it has arrived, and telling it what it did before it goes. That envelope belongs
     * here rather than in a procedure: a procedure a project edits could lose it, and a second trigger
     * would have to remember to repeat it.
     */
    private function brief(Profile $profile, Duty $duty, string $subject): string
    {
        $role = $profile->role($duty->agent)->unwrapOr('');
        $procedure = $profile->procedure($duty->procedure)->unwrapOr('');
        $holes = Holes::none()
            ->with('agent', $duty->agent)
            ->with('procedure', $duty->procedure)
            ->with('subject', $subject)
            ->with('role', $role)
            ->with('work', $procedure)
            ->with('binary', Binary::in($this->root));

        foreach ($profile->reminder('dispatch', $holes) as $envelope) {
            return $envelope;
        }

        return $holes->fill($this->shipped());
    }

    /**
     * The envelope when a profile has not written its own. It is stated here so a project that never
     * takes the template still gets an agent that announces itself — an agent nobody knows started is
     * the failure this whole layer exists to remove.
     */
    private function shipped(): string
    {
        return <<<'TEXT'
            You are `{agent}`, dispatched automatically. This is WHO you are:

            {role}

            This is WHAT to do, against {subject}:

            {work}

            TELL THE ORCHESTRATOR AT BOTH ENDS. It cannot see you otherwise, and work that finishes
            without anybody being told is the failure this whole arrangement exists to remove. Find it
            with `ListAgents` — it is the session on this machine that is not you — and `SendMessage` it
            once now, one line, saying you have started on {subject}; then once before you stop, with a
            SHORT account of what you actually did. If messaging is unavailable to you, say so in your
            report rather than going quiet, and file it instead:

              {binary} build report {procedure} --ran="<the check you ran>"

            You hold `{procedure}` on the board. If more work is queued for you it arrives automatically
            in this same conversation, so what you learn now is worth keeping for it.
            TEXT;
    }

    private function logFor(Duty $duty): string
    {
        return '.' . $duty->agent . '.log';
    }

    /**
     * A subject short enough to read in a line. A sha shortens; anything else is already a name.
     */
    private function short(string $subject): string
    {
        return strlen($subject) === 40 ? substr($subject, 0, 7) : $subject;
    }
}
