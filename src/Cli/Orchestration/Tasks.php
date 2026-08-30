<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The work this session has in front of it, as three folders — `tasks/queue`, `tasks/active`,
 * `tasks/history`. A task changes state by being MOVED between them, so the filesystem holds the truth:
 * `git status` shows what moved, nothing has to be kept in step with a field, and a task nobody closed
 * is a file still sitting in `active/`. What a task IS is its {@see Task} file; what it is BENEATH is
 * its {@see TaskId}, so nothing has to be nested to say so.
 */
final readonly class Tasks
{
    private const string FOLDER = 'tasks';

    private const string EXTENSION = '*.md';

    public function __construct(public string $root) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self($workspace->path(self::FOLDER));
    }

    /**
     * Where tasks in $state are kept — one folder per state, which is the whole storage design.
     */
    private function folder(TaskState $state): string
    {
        return $this->root . '/' . $state->value;
    }

    /**
     * Every task in every state, in address order — one ordered listing that reads as the tree, because a
     * parent's number sorts immediately above its own subtasks.
     *
     * @return list<Task>
     */
    public function all(): array
    {
        return $this->inState(...TaskState::cases());
    }

    /**
     * Every task standing in one of $states, in address order.
     *
     * @return list<Task>
     */
    public function inState(TaskState ...$states): array
    {
        $tasks = [];

        foreach ($states as $state) {
            foreach (glob($this->folder($state) . '/' . self::EXTENSION) ?: [] as $path) {
                foreach (Task::at($path, $state) as $task) {
                    $tasks[] = $task;
                }
            }
        }

        usort($tasks, static fn (Task $a, Task $b): int => TaskId::compare($a->id, $b->id));

        return $tasks;
    }

    /**
     * The task at $id, whatever state it is in — none when nothing carries that number. A closed task
     * still answers to its address, which is what makes a number quotable long after the work is done.
     *
     * @return Option<Task>
     */
    public function find(TaskId $id): Option
    {
        foreach ($this->all() as $task) {
            if ($task->id->equals($id)) {
                return Option::some($task);
            }
        }

        return Option::none();
    }

    /**
     * Queue a new task beneath $under — {@see TaskId::board()} for one of its own, a task's id for a
     * subtask of it. None when the file could not be written, so a caller never announces a number that
     * does not exist.
     *
     * @return Option<Task>
     */
    public function add(TaskId $under, string $title, string $why): Option
    {
        $id = $under->child($this->highestBeneath($under) + 1);

        return Task::open($this->folder(TaskState::Queued), $id, $title, $why);
    }

    /**
     * Move $task into $to and log that it got there. None when the file could not be moved — the log line
     * is written AFTER the move, so a task can never claim a state its file is not filed under.
     *
     * @return Option<Task>
     */
    public function move(Task $task, TaskState $to, string $reason): Option
    {
        $folder = $this->folder($to);

        if (! is_dir($folder) && ! @mkdir($folder, 0777, true) && ! is_dir($folder)) {
            return Option::none();
        }

        $path = $folder . '/' . basename($task->path);

        if (! @rename($task->path, $path)) {
            return Option::none();
        }

        $moved = $task->movedTo($path, $to);
        $moved->log($to, $reason);

        return Option::some($moved);
    }

    /**
     * The highest number anybody has used directly beneath $under, 0 when nobody has. It is read from the
     * FILES rather than kept in a counter beside them: `history/` keeps every task this session ever had,
     * so the files can only ever say a bigger number, where a counter can drift from them and hand out a
     * number somebody is already using as an address.
     */
    private function highestBeneath(TaskId $under): int
    {
        $highest = 0;

        foreach ($this->all() as $task) {
            if ($task->id->isChildOf($under)) {
                $highest = max($highest, $task->id->number());
            }
        }

        return $highest;
    }
}
