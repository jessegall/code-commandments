<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The orchestrator's plan: a main plan, and a sidequest nested under whatever was being done when it
 * appeared, as deep as it goes. The PATH is the breadcrumb, so finishing means deleting a folder and
 * what is left says where you were — which is why it is a folder tree rather than a record with a parent
 * field: the path IS the parent chain, deleting IS finishing, and a sidequest nobody closed shows up in
 * `git status` as a directory nobody removed.
 */
final readonly class Plan
{
    private const string BODY = 'README.md';

    private const string CHILDREN = 'sidequest';

    public function __construct(public string $root) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self($workspace->sessionDir() . '/plan');
    }

    public function exists(): bool
    {
        return is_file($this->root . '/' . self::BODY);
    }

    /**
     * Start the plan itself. Its body is the one thing a reader wants first, so an empty plan says what
     * it is for rather than being an empty folder.
     */
    public function open(string $title): bool
    {
        if ($this->exists()) {
            return false;
        }

        return $this->put($this->root, "# {$title}\n");
    }

    /**
     * A sidequest under $path — a detour from THERE, which is why the caller passes where it is standing
     * rather than a path it had to spell.
     *
     * @param  list<string>  $path
     */
    public function add(array $path, string $name, string $why): bool
    {
        $dir = $this->dirFor([...$path, $name]);

        if (is_dir($dir)) {
            return false;
        }

        return $this->put($dir, "# {$name}\n\n{$why}\n");
    }

    /**
     * Close the level at $path: its REASON goes up into its parent's body, and the folder goes. The
     * reason is the half worth keeping — a conclusion can be re-derived, where a reason is what lets a
     * later reader see whether the premise still holds.
     *
     * @param  list<string>  $path
     */
    public function close(array $path, string $reason): bool
    {
        $dir = $this->dirFor($path);

        if ($path === [] || ! is_dir($dir)) {
            return false;
        }

        $parent = $this->dirFor(array_slice($path, 0, -1)) . '/' . self::BODY;
        $name = $path[count($path) - 1];

        if (is_file($parent)) {
            File::write($parent, rtrim((string) file_get_contents($parent), "\n") . "\n\n- **{$name}** — {$reason}\n");
        }

        $this->remove($dir);

        return true;
    }

    /**
     * Every live level, deepest last, each as its path from the root. The whole shape in one read, which
     * is what a compacted reader needs before anything else.
     *
     * @return list<list<string>>
     */
    public function levels(): array
    {
        return $this->exists() ? [[], ...$this->below([])] : [];
    }

    /**
     * When $path was last touched — the newest mtime anywhere beneath it, so a parent is only as stale as
     * its most recent child. None when it is not there.
     *
     * @param  list<string>  $path
     * @return Option<int>
     */
    public function touched(array $path): Option
    {
        $dir = $this->dirFor($path);

        if (! is_dir($dir)) {
            return Option::none();
        }

        $newest = (int) @filemtime($dir . '/' . self::BODY);

        foreach ($this->below($path) as $child) {
            foreach ($this->touched($child) as $at) {
                $newest = max($newest, $at);
            }
        }

        return Option::some($newest);
    }

    /**
     * The title a level goes by — its body's first heading, else the folder's own name.
     *
     * @param  list<string>  $path
     */
    public function title(array $path): string
    {
        $body = $this->dirFor($path) . '/' . self::BODY;

        foreach (explode("\n", is_file($body) ? (string) file_get_contents($body) : '') as $line) {
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }

        return $path === [] ? 'plan' : $path[count($path) - 1];
    }

    /**
     * WHY a level exists — the first line of prose under its heading. `where` prints it beside each step,
     * because a path alone tells you where you are standing and not what you came for.
     *
     * @param  list<string>  $path
     */
    public function why(array $path): string
    {
        $body = $this->dirFor($path) . '/' . self::BODY;

        foreach (explode("\n", is_file($body) ? (string) file_get_contents($body) : '') as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, '-')) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Is $path a level that is actually there? A cursor pointing at a closed level would otherwise report
     * a position nothing occupies.
     *
     * @param  list<string>  $path
     */
    public function has(array $path): bool
    {
        return is_file($this->dirFor($path) . '/' . self::BODY);
    }

    /**
     * @param  list<string>  $path
     */
    public function dirFor(array $path): string
    {
        $dir = $this->root;

        foreach ($path as $step) {
            $dir .= '/' . self::CHILDREN . '/' . $step;
        }

        return $dir;
    }

    /**
     * Every level beneath $path, depth first, so a listing reads as the tree rather than as a set.
     *
     * @param  list<string>  $path
     * @return list<list<string>>
     */
    private function below(array $path): array
    {
        $found = [];

        foreach (glob($this->dirFor($path) . '/' . self::CHILDREN . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $child = [...$path, basename($dir)];

            if (! $this->has($child)) {
                continue;
            }

            $found[] = $child;
            $found = [...$found, ...$this->below($child)];
        }

        return $found;
    }

    /**
     * Write a level's body, making its folder first. {@see File::write} writes through a temporary file
     * beside the target, so it FAILS on a directory that is not there yet — and answers false rather
     * than throwing. Answering that back is the point: a plan that reported a level it had not written
     * would be a tool stating a fact it did not measure.
     */
    private function put(string $dir, string $body): bool
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
            return false;
        }

        return File::write($dir . '/' . self::BODY, $body);
    }

    private function remove(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->remove($entry);

                continue;
            }

            @unlink($entry);
        }

        @rmdir($dir);
    }
}
