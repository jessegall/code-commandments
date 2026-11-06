<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Orchestration\Duty;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Checkout;
use JesseGall\CodeCommandments\Cli\Orchestration\Profile;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Cli\Orchestration\Queue;
use JesseGall\CodeCommandments\Hooks\HeadMark;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\CodeCommandments\Support\Detached;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * The `commit` trigger. A commit has landed, so there is something FIXED to read — a diff against the
 * working tree moves under its reader where a sha does not. What runs is the profile's to say
 * (`orchestrate on commit <agent> <procedure>`); the hook knows the moment and nothing else.
 */
final class CommitTrigger extends Hook
{
    /**
     * The moment this answers, as a profile names it.
     */
    private const string TRIGGER = 'commit';

    /**
     * What this hook's HEAD mark is filed under ({@see HeadMark::named}).
     */
    private const string MARK = 'commit-trigger';

    /**
     * $detached is the seam: everything else here can be read off disk afterwards, and the one act that
     * cannot is starting an agent. Injected so a test exercises the dispatch without launching one.
     */
    public function __construct(HookIO $io = new HookIO, private readonly Detached $detached = new Detached)
    {
        parent::__construct($io);
    }

    public function summary(): string
    {
        return 'When a commit lands, dispatches whatever the profile bound to the `commit` trigger.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse')];
    }

    /**
     * Never blocks. The commit is already made, so what follows is worth having and not worth holding the
     * next command for.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isGitCommit()) {
            return $this->pass();
        }

        if (! $this->landedHere($event)) {
            return $this->pass();
        }

        $said = [];

        foreach (Profiles::inForce($event->sessionWorkspace()) as $profile) {
            foreach ($profile->boundTo(self::TRIGGER) as $duty) {
                foreach ($this->dispatch($event, $profile, $duty) as $line) {
                    $said[] = $line;
                }
            }
        }

        return $said === [] ? $this->pass() : $this->quietly($event, implode("\n", $said));
    }

    /**
     * Did the commit land in THIS checkout? The moment fires for every `git commit` the session runs,
     * and a builder checkpointing in its own lane runs a great many: three lanes checkpointing per
     * widget is fifteen reviews of drafts for work that lands as three merges.
     *
     * So the fact is narrower than the moment — this checkout's HEAD MOVED — and it is remembered
     * rather than read, since no payload carries where a commit landed. Marking it here is also what
     * makes one commit answered once: a second firing on the same sha finds it already answered.
     */
    private function landedHere(HookEvent $event): bool
    {
        return HeadMark::named($event->workspace(), self::MARK, 'is answered once per commit')
            ->movedTo($this->git()->head($event->root));
    }

    /**
     * Dispatch one binding, and say what happened. The CLAIM is the lock: a second commit while the first
     * review is still reading is refused by the board rather than by a flag of our own, so the work shows
     * up where the orchestrator already looks for who is holding what.
     *
     * @return list<string>
     */
    private function dispatch(HookEvent $event, Profile $profile, Duty $duty): array
    {
        $item = $duty->procedure;
        $sha = $this->git()->head($event->root);

        if ($profile->procedure($duty->procedure)->isNone()) {
            return ["Code Commandments — `{$profile->name}` binds `{$item}` on commit but has not written it."];
        }

        $queue = Queue::forAgent($event->sessionWorkspace(), $duty->agent);

        if (! $queue->take($sha)) {
            return [sprintf(
                'Code Commandments — `%s` is still reading %s, so %s is QUEUED (%d waiting). It starts on its own.',
                $duty->agent,
                substr($queue->running(), 0, 7),
                substr($sha, 0, 7),
                count($queue->waiting()),
            )];
        }

        Board::inSession($event->sessionWorkspace())->claim($item, $duty->agent, gmdate('H:i'));
        $this->spawn($event, $profile, $duty);

        return [sprintf(
            'Code Commandments — `%s` is running `%s` against %s in its own lane. It holds `%s` on the board; '
                . 'its words land in %s.',
            $duty->agent,
            $duty->procedure,
            substr($sha, 0, 7),
            $item,
            $event->sessionWorkspace()->relative($this->logFor($duty)),
        )];
    }

    /**
     * Start it and let go. Everything happens inside ONE detached shell — standing the lane up, then the
     * agent — so a lane that takes a minute to prepare does not hold the commit that triggered it.
     *
     * The agent runs IN THE LANE and the DRAIN runs in the checkout the queue belongs to. Those are two
     * different directories and the difference is load-bearing: `queue next` resolves its own workspace
     * from the project it runs in, so run in the lane it reads a session folder nothing writes to, and a
     * second commit queues and is never picked up.
     */
    private function spawn(HookEvent $event, Profile $profile, Duty $duty): void
    {
        $workspace = $event->sessionWorkspace();
        $binary = Binary::in($event->root);
        $lane = Checkout::homeFor($workspace, $event->root) . '/' . $duty->agent;
        $log = $workspace->path($this->logFor($duty));
        $prompt = $workspace->path('.' . $duty->agent . '.prompt');
        $queue = Queue::forAgent($workspace, $duty->agent);
        $resuming = $queue->hasConversation();
        $conversation = $queue->conversation();

        File::write($prompt, $this->brief($event, $profile, $duty, $resuming));

        // The prompt goes on STDIN, never in argv: a brief carries a whole role and procedure, and argv
        // has a length nobody discovers until the day a procedure grows past it.
        //
        // The LOOP is what drains the queue. `queue next` prints the following brief or prints nothing,
        // and nothing is the signal to stop — a loop that must parse a sentence to learn it is finished
        // is one that eventually misreads it. So a run of five commits is read by ONE conversation, in
        // order, rather than by five that each know only their own diff.
        $inner = sprintf(
            '%s lane open %s >> %s 2>&1; cd %s 2>/dev/null || cd %s; c=%s; '
                . 'while :; do claude --print $c < %s >> %s 2>&1; c=%s; '
                . '(cd %s && %s queue next %s) > %s 2>/dev/null; [ -s %s ] || break; done',
            escapeshellarg($binary),
            escapeshellarg($duty->agent),
            escapeshellarg($log),
            escapeshellarg($lane),
            escapeshellarg($event->root),
            $resuming ? '--resume ' . escapeshellarg($conversation) : '--session-id ' . escapeshellarg($conversation),
            escapeshellarg($prompt),
            escapeshellarg($log),
            '--resume ' . escapeshellarg($conversation),
            escapeshellarg($event->root),
            escapeshellarg($binary),
            escapeshellarg($duty->agent),
            escapeshellarg($prompt),
            escapeshellarg($prompt),
        );

        $this->detached->start($inner, $log);
    }

    /**
     * What the agent is told. A CONTINUING one already holds its role and its procedure, so restating
     * them would spend its context re-reading what it knows — it needs the new sha and nothing else.
     */
    private function brief(HookEvent $event, Profile $profile, Duty $duty, bool $started): string
    {
        $binary = Binary::in($event->root);
        $sha = $this->git()->head($event->root);

        if ($started) {
            return "Another commit landed: {$sha}. Carry out the same procedure against it, then "
                . "`{$binary} build report {$duty->procedure} --ran=\"<the check>\"` and stop.";
        }

        $session = $event->sessionId();
        $role = $profile->role($duty->agent)->unwrapOr('');
        $procedure = $profile->procedure($duty->procedure)->unwrapOr('');

        return <<<TEXT
            You are `{$duty->agent}`, dispatched automatically because a commit landed. This is WHO you
            are:

            {$role}

            This is WHAT to do, against commit {$sha}:

            {$procedure}

            TELL THE ORCHESTRATOR, at both ends. It cannot see you otherwise — you are a session of your
            own, and a board it has to remember to read is one it reads late.

            Find it with `ListAgents`: it is the session on this machine that is not you, and its Claude
            session id is `{$session}`. Then `SendMessage` it — once now, one line, saying you have
            started on {$sha}; and once when you finish, with what you found. If `SendMessage` is not
            available to you, say so in your report rather than staying silent, and file it instead:

              {$binary} build report {$duty->procedure} --ran="<the check you ran>"

            Then stop. If more commits landed while you were reading you will be handed the next one
            automatically, so what you learn now is worth keeping for it.
            TEXT;
    }

    /**
     * Where this binding's words land. Named for the agent, since two agents answering one trigger must
     * not write over each other.
     */
    private function logFor(Duty $duty): string
    {
        return '.' . $duty->agent . '.log';
    }
}
