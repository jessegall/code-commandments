<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\File;
use JesseGall\PhpTypes\Option;

/**
 * One task — one markdown file, addressed by its {@see TaskId}. It holds a title, the why it was opened
 * for, and a log of every state it has entered; nobody types any of that by hand, so it stays short
 * enough to read whole and every field means the same thing in every file.
 */
final readonly class Task
{
    private const string EXTENSION = '.md';

    /**
     * Where the address stops and the words begin in a filename — `002.1-probe-the-parser.md`.
     */
    private const string SEPARATOR = '-';

    /**
     * How long a title's slug may run in a filename. A task's words are in the file; the name only has
     * to be recognisable in a directory listing.
     */
    private const int SLUG = 48;

    private const string STAMP = 'Y-m-d H:i';

    /**
     * What divides a log line's stamp from its reason. Written and read back through the one constant, so
     * an outcome can never be filed under a dash the reader is not looking for.
     */
    private const string REASON = ' — ';

    public function __construct(
        public TaskId $id,
        public TaskState $state,
        public string $path,
        public string $title,
        public string $why,
    ) {}

    /**
     * The task the file at $path holds, none when the name carries no address — a stray file in one of
     * the three folders is not a task, and reading it as one would give it a number nothing assigned.
     *
     * @return Option<self>
     */
    public static function at(string $path, TaskState $state): Option
    {
        $name = basename($path, self::EXTENSION);
        $address = explode(self::SEPARATOR, $name, 2)[0];

        return TaskId::parse($address)->map(function (TaskId $id) use ($path, $state, $name): self {
            $body = self::contentsOf($path);

            return new self($id, $state, $path, self::titleIn($body, $name), self::whyIn($body));
        });
    }

    /**
     * Write a brand new task into $folder and hand back what was written. It answers none rather than
     * throwing when the write fails, so a caller never reports a number it did not manage to file.
     *
     * @return Option<self>
     */
    public static function open(string $folder, TaskId $id, string $title, string $why): Option
    {
        $path = $folder . '/' . $id->render() . self::SEPARATOR . self::slug($title) . self::EXTENSION;
        $blocks = ["# {$title}"];

        if ($why !== '') {
            $blocks[] = $why;
        }

        $blocks[] = '- ' . TaskState::Queued->entered() . ' ' . date(self::STAMP);

        if (! File::write($path, implode("\n\n", $blocks) . "\n")) {
            return Option::none();
        }

        return self::at($path, TaskState::Queued);
    }

    /**
     * The file's whole text — what `task show` prints, and what an orchestrator pastes into a brief.
     */
    public function body(): string
    {
        return self::contentsOf($this->path);
    }

    /**
     * When the file was last written. `task stale` reads it to name the work nobody has touched, which
     * is the line that is missing when an active task sits open all evening unmentioned.
     */
    public function touched(): int
    {
        return (int) @filemtime($this->path);
    }

    /**
     * Record that this task has entered $state, with the REASON where there is one. The reason is the
     * half worth keeping: a conclusion can be re-derived, where a reason is what lets a later reader see
     * whether the premise still holds — so it goes in the file that survives into `history/`.
     */
    public function log(TaskState $state, string $reason): bool
    {
        $line = '- ' . $state->entered() . ' ' . date(self::STAMP) . ($reason === '' ? '' : self::REASON . $reason);

        return File::write($this->path, rtrim($this->body(), "\n") . "\n{$line}\n");
    }

    /**
     * The same task, filed at $path in $state — how a move reports where the file went without re-reading
     * words that did not change.
     */
    public function movedTo(string $path, TaskState $state): self
    {
        return new self($this->id, $state, $path, $this->title, $this->why);
    }

    /**
     * WHAT CAME OF IT — the reason on the last state the log recorded, empty where that state was entered
     * without one. It is the half of a closed task worth reading, so `task history` prints it beside the
     * title rather than making a reader open the file to learn how the work ended.
     */
    public function outcome(): string
    {
        $outcome = '';

        foreach (explode("\n", $this->body()) as $line) {
            if (str_starts_with($line, '- ')) {
                $outcome = self::reasonIn($line);
            }
        }

        return $outcome;
    }

    /**
     * The one line a listing gives a task: its mark, its address, its title, and the why beside it.
     */
    public function line(): string
    {
        return str_repeat('  ', $this->id->depth() - 1)
            . $this->state->mark() . ' ' . $this->id->render() . '  ' . $this->title
            . ($this->why === '' ? '' : ' — ' . $this->why);
    }

    /**
     * $title as a filename — lowercase words joined by dashes, cut to something a listing can show.
     */
    private static function slug(string $title): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', self::SEPARATOR, strtolower($title)), self::SEPARATOR);

        return $slug === '' ? 'task' : substr($slug, 0, self::SLUG);
    }

    /**
     * The heading the file opens with, falling back to the filename for a file whose heading somebody
     * removed — a task with no title still has to be addressable.
     */
    private static function titleIn(string $body, string $name): string
    {
        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }

        return $name;
    }

    /**
     * WHY the task exists — the prose under the heading, which is neither the heading nor the log.
     */
    private static function whyIn(string $body): string
    {
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, '-')) {
                return $line;
            }
        }

        return '';
    }

    /**
     * The reason a log line carries, empty for one written without.
     */
    private static function reasonIn(string $line): string
    {
        return trim(explode(self::REASON, $line, 2)[1] ?? '');
    }

    private static function contentsOf(string $path): string
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
