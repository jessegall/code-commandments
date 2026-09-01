<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Task;

/**
 * Where a task stands — and the folder it therefore sits in. The state is not a field anybody keeps in
 * step: MOVING the file IS the change, so the filesystem holds the truth, `git status` shows it, and a
 * state nobody wrote down cannot disagree with a file nobody moved.
 */
enum TaskState: string
{
    case Queued = 'queue';

    case Active = 'active';

    case Done = 'history';

    /**
     * The states a LIVE task can be in — the board. Done is left out because a board is what is still
     * owed; what came of everything else is `task history`.
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Queued, self::Active];
    }

    /**
     * The word the task's own log writes when it enters this state. It is the state's to know, so the
     * log a reader finds in `history/` and the folder the file sits in can only ever say the same thing.
     */
    public function entered(): string
    {
        return match ($this) {
            self::Queued => 'queued',
            self::Active => 'started',
            self::Done => 'done',
        };
    }

    /**
     * How a task in this state is marked in a listing — the column that answers "what is being worked
     * on" before any word is read.
     */
    public function mark(): string
    {
        return match ($this) {
            self::Queued => '○',
            self::Active => '●',
            self::Done => '✓',
        };
    }
}
