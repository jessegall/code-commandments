<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\Workspace;

/**
 * The judged files a tool CHANGED, for the tools that do not say which. A `Write` names its
 * `file_path`; a Bash heredoc, a `sed -i` or a script names nothing, and reading the command to
 * guess a path is how a parser starts lying. So this asks the TREE instead: which of the files this
 * project judges are newer than the last time we looked.
 */
final class TouchedSources
{
    /**
     * The extensions both engines judge — what a walk collects, so a hook is not handed a lockfile
     * or a snapshot to parse as source.
     */
    private const array JUDGED = ['php', 'vue', 'ts'];

    public function __construct(
        private readonly Workspace $workspace,
        private readonly string $root,
        private readonly Config $config,
    ) {}

    /**
     * The judged files modified since the previous claim — newest first, at most $limit of them —
     * and the mark MOVES, so the next call sees only what changed after this one. Empty on the first
     * call of a session: there is no "since" yet, and a tree of unchanged files is not news.
     *
     * @return list<string>
     */
    public function claim(int $limit): array
    {
        $file = $this->state();
        $since = $file->read()->int('marked_at');
        $now = time();

        $this->markAt($file, $now);

        if ($since === 0) {
            return [];
        }

        $touched = $this->modifiedSince($since);

        // Newest first, so a burst that overruns the limit reports what was written LAST — the edit
        // the agent has in mind, rather than whichever file a directory walk reached first.
        arsort($touched);

        return array_slice(array_keys($touched), 0, $limit);
    }

    /**
     * Move the mark past everything as it stands, claiming nothing — what a tool that NAMED its own
     * file calls, so the file it just wrote is not claimed a second time by the next Bash call.
     */
    public function markSeen(): void
    {
        $this->markAt($this->state(), time());
    }

    /**
     * Every judged file under the project's source roots modified at or after $since, as
     * `path => mtime`. Excluded paths are pruned, exactly as a scan prunes them.
     *
     * @return array<string, int>
     */
    private function modifiedSince(int $since): array
    {
        $home = rtrim($this->root, '/');
        $excluded = ExcludedPaths::under($home, $this->config->excludedPaths());
        $touched = [];

        foreach ($this->config->sourceRoots() as $relative) {
            $dir = $relative === '.' ? $home : $home . '/' . trim($relative, '/');

            foreach ($this->walk($dir, $excluded) as $path) {
                $mtime = (int) @filemtime($path);

                // At or after, never strictly after: an mtime has one-second resolution, so a file
                // written in the same second the mark was set would otherwise be missed for ever.
                // The cost of the boundary is one repeated nudge; the cost of the other is silence.
                if ($mtime >= $since) {
                    $touched[$path] = $mtime;
                }
            }
        }

        return $touched;
    }

    /**
     * Every judged file under $dir, pruning what the project excluded and every dot-directory (a
     * `.git` is not source, and walking one costs more than the whole of the rest).
     *
     * @return list<string>
     */
    private function walk(string $dir, ExcludedPaths $excluded): array
    {
        if (! is_dir($dir) || $excluded->covers($dir)) {
            return [];
        }

        $found = [];

        foreach (scandir($dir) ?: [] as $entry) {
            $path = $dir . '/' . $entry;

            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.') || is_link($path)) {
                continue;
            }

            if (is_dir($path)) {
                $found = [...$found, ...$this->walk($path, $excluded)];

                continue;
            }

            if (in_array(pathinfo($path, PATHINFO_EXTENSION), self::JUDGED, true) && ! $excluded->covers($path)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    private function markAt(StateFile $file, int $when): void
    {
        $file->write($file->read()->with(marked_at: $when));
    }

    private function state(): StateFile
    {
        return new StateFile($this->workspace->path('.touched-sources'), new Legend(
            'Code-commandments — where the per-edit check has looked up to.',
            ['marked_at' => 'the unix time of the last look; a judged file newer than this is one a tool has changed since'],
            defaults: new State(marked_at: 0),
        ));
    }
}
