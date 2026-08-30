<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * `commandments task` — the work this session has in front of it, one markdown file per task, addressed
 * by a NUMBER. The number is what makes a task quotable into a worker's brief; the folder it sits in is
 * its state; and every field in the file is written from here, so a task stays short enough to read whole.
 */
final class TaskCommand implements Command
{
    /**
     * How long a task may sit untouched before `task stale` names it, when nobody says otherwise.
     */
    private const int STALE = 60;

    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['task', 'tasks'];
    }

    public function help(): Help
    {
        return Help::of('The work in front of this session — numbered tasks, one markdown file each, moved between queue, active and history.')
            ->form('task', 'the board — every task still owed, in address order, subtasks beneath their parent')
            ->form('task add "<title>" ["<why>"]', 'queue one, and print the NUMBER it was given')
            ->form('task add --under=<id> "<title>" ["<why>"]', 'queue a SUBTASK of <id> — it carries <id>\'s number (`002.1`), it does not move into a folder of its own')
            ->option('--under=ID', 'with `add`: the task the new one is a subtask of')
            ->form('task start <id>', 'begin it — the file moves from `queue/` into `active/`')
            ->form('task done <id> "<what came of it>"', 'close it — the file moves into `history/`, and the reason goes in its log')
            ->form('task show <id>', 'read one out, whole — what you paste into a worker\'s brief')
            ->form('task history', 'what has been closed, and what came of each')
            ->form('task stale [--for=N]', 'active tasks nobody has touched for N minutes (default ' . self::STALE . ')')
            ->option('--for=N', 'with `stale`: how many minutes untouched counts as stale (default ' . self::STALE . ')')
            ->note('A task is ADDRESSED by its number, not by where it sits. That is the whole design: `002.1` '
                . 'means the same thing in a listing, in a filename and in a brief handed to a worker, where a '
                . 'folder path stops meaning anything the moment the work moves on. A subtask carries its '
                . 'parent\'s number and nothing else — there is no nesting to walk, and no cursor to be standing in.')
            ->note('The three folders under `.commandments/sessions/<id>/tasks/` ARE the state: moving the file '
                . 'is the change, so `git status` shows it, nothing has to be kept in step with a field, and a '
                . 'task nobody closed is a file still sitting in `active/`. `history/` keeps every task this '
                . 'session ever had — which is also why a number is never handed out twice.');
    }

    public function run(Input $input): int
    {
        $tasks = Tasks::inSession(Workspace::ofSession($this->io->projectRoot()));
        $verb = $input->firstArgument()->unwrapOr('');

        return match ($verb) {
            'add' => $this->add($tasks, $input),
            'start' => $this->start($tasks, $input->argument(1)->unwrapOr('')),
            'done' => $this->done($tasks, $input->argument(1)->unwrapOr(''), $input->argument(2)->unwrapOr('')),
            'show' => $this->show($tasks, $input->argument(1)->unwrapOr('')),
            'history' => $this->history($tasks),
            'stale' => $this->stale($tasks, (int) $input->option('for')->unwrapOr((string) self::STALE)),
            '' => $this->board($tasks),
            default => HelpScreen::usage($this, "No `task {$verb}`."),
        };
    }

    /**
     * Queue one. The number is assigned rather than typed — it is the highest anybody has used beneath
     * that parent plus one, read from the files themselves, so nothing has to be remembered between runs.
     */
    private function add(Tasks $tasks, Input $input): int
    {
        $title = $input->argument(1)->unwrapOr('');

        if ($title === '') {
            return HelpScreen::usage($this, 'Say what the task is: `commandments task add "<title>" "<why>"`.');
        }

        $under = $this->parentIn($tasks, $input);

        if ($under->isNone()) {
            return $this->noSuchTask($input->option('under')->unwrapOr(''));
        }

        $added = $tasks->add($under->unwrap(), $title, $input->argument(2)->unwrapOr(''));

        if ($added->isNone()) {
            return $this->console->refuse('The task could not be written — check that ' . $tasks->root . ' is writable.');
        }

        return $this->console->say('▸ ' . $added->unwrap()->id->render() . ' queued — ' . $title);
    }

    /**
     * Begin it. The file moves into `active/`, which is the change — there is no state field to write and
     * so no way for the two to disagree.
     */
    private function start(Tasks $tasks, string $id): int
    {
        $found = $this->taskNamed($tasks, $id);

        if ($found->isNone()) {
            return $this->noSuchTask($id);
        }

        $task = $found->unwrap();

        if ($task->state === TaskState::Active) {
            return $this->console->refuse("`{$task->id->render()}` is already active.");
        }

        return $this->moved($tasks, $task, TaskState::Active, '', '▸ ' . $task->id->render() . ' started — ' . $task->title);
    }

    /**
     * Close it into `history/`, with what came of it. The reason is required because it is the half worth
     * keeping: a conclusion can be re-derived, where a reason is what lets a later reader see whether the
     * premise still holds.
     */
    private function done(Tasks $tasks, string $id, string $reason): int
    {
        $found = $this->taskNamed($tasks, $id);

        if ($found->isNone()) {
            return $this->noSuchTask($id);
        }

        if ($reason === '') {
            return HelpScreen::usage($this, 'Say what came of it: `commandments task done ' . $id . ' "<what came of it>"` — it is what history keeps.');
        }

        $task = $found->unwrap();

        if ($task->state === TaskState::Done) {
            return $this->console->refuse("`{$task->id->render()}` is already closed.", '  `commandments task show ' . $task->id->render() . '` reads its log.');
        }

        return $this->moved($tasks, $task, TaskState::Done, $reason, '✓ ' . $task->id->render() . ' done — ' . $task->title);
    }

    private function show(Tasks $tasks, string $id): int
    {
        $found = $this->taskNamed($tasks, $id);

        if ($found->isNone()) {
            return $this->noSuchTask($id);
        }

        return $this->console->say(rtrim($found->unwrap()->body(), "\n"));
    }

    /**
     * The board — everything still owed, in address order, so one listing reads as the tree.
     */
    private function board(Tasks $tasks): int
    {
        $live = $tasks->inState(...TaskState::live());

        if ($live === []) {
            return $this->console->say('Nothing queued. `commandments task add "<title>"` starts one.');
        }

        return $this->console->say(...array_map(static fn (Task $task): string => $task->line(), $live));
    }

    /**
     * What has been closed, and what came of each — the log the folder keeps.
     */
    private function history(Tasks $tasks): int
    {
        $closed = $tasks->inState(TaskState::Done);

        if ($closed === []) {
            return $this->console->say('Nothing closed yet.');
        }

        $lines = [];

        foreach ($closed as $task) {
            $outcome = $task->outcome();
            $lines[] = $task->state->mark() . ' ' . $task->id->render() . '  ' . $task->title;
            $lines[] = '    ' . ($outcome === '' ? 'closed without a reason' : $outcome);
        }

        return $this->console->say(...$lines);
    }

    /**
     * Active work nobody has touched for $minutes — the line that is missing when a task sits open all
     * evening unmentioned.
     */
    private function stale(Tasks $tasks, int $minutes): int
    {
        $cutoff = time() - ($minutes * 60);
        $stale = [];

        foreach ($tasks->inState(TaskState::Active) as $task) {
            if ($task->touched() < $cutoff) {
                $stale[] = '  ' . $task->id->render() . '  ' . $task->title . ' — ' . intdiv(time() - $task->touched(), 60) . 'm';
            }
        }

        return $stale === []
            ? $this->console->say("Nothing untouched for {$minutes}m.")
            : $this->console->say("Untouched for {$minutes}m or more:", ...$stale);
    }

    /**
     * Carry out $to, or say the move did not happen. A rename that failed silently would leave the board
     * showing a state the file is not filed under, which is the one thing this design exists to prevent.
     */
    private function moved(Tasks $tasks, Task $task, TaskState $to, string $reason, string $said): int
    {
        if ($tasks->move($task, $to, $reason)->isNone()) {
            return $this->console->refuse("`{$task->id->render()}` could not be moved into `{$to->value}/`.");
        }

        return $this->console->say($said);
    }

    /**
     * What the new task goes beneath — the board without `--under`, the named task with it. None when
     * `--under` names nothing, so a subtask is never quietly filed as a top-level one.
     *
     * @return Option<TaskId>
     */
    private function parentIn(Tasks $tasks, Input $input): Option
    {
        $under = $input->option('under');

        if ($under->isNone()) {
            return Option::some(TaskId::board());
        }

        return $this->taskNamed($tasks, $under->unwrap())->map(static fn (Task $task): TaskId => $task->id);
    }

    /**
     * The task $id addresses, in whatever state it stands. A closed task still answers to its number,
     * which is what makes an address worth quoting long after the work is done.
     *
     * @return Option<Task>
     */
    private function taskNamed(Tasks $tasks, string $id): Option
    {
        return TaskId::parse($id)->andThen($tasks->find(...));
    }

    private function noSuchTask(string $id): int
    {
        return $this->console->refuse(
            $id === '' ? 'Name the task by its number.' : "No task `{$id}`.",
            '  `commandments task` lists what is open, `commandments task history` what is closed.',
        );
    }
}
