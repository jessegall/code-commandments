<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Workspace;

/**
 * The judge worklist file — `sins/sins.md` inside the session folder — and its timestamped archives.
 * Owns knowing where they live, how they rotate and how to clear them. They sit in a folder of their
 * own because generated output buries the session STATE beside it; the folder's own note says so.
 */
final class Checklist
{
    /**
     * What the folder tells the next person who opens it — see {@see explain}.
     */
    private const string NOTES = 'README.md';

    /**
     * How many past checklists to keep alongside the live one.
     */
    private const int KEEP_ARCHIVES = 5;

    public function __construct(private readonly string $path) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self($workspace->checklist());
    }

    /**
     * Make $target writable: its folder exists, and when it IS the session's own folder, it says
     * what it is. Any other $target is a path the USER named with `--checklist=FILE`, so nothing
     * but the checklist is written beside it.
     *
     * The session folder is touched as well. Its mtime is what {@see Workspace::prune} sweeps a
     * stale session by, and writing into a subfolder no longer moves it — the property belonged to
     * the checklist's old home, not to the checklist.
     */
    public static function prepare(string $target, Workspace $workspace): bool
    {
        $folder = dirname($target);

        if (! is_dir($folder) && ! @mkdir($folder, 0755, true) && ! is_dir($folder)) {
            return false;
        }

        if ($target === $workspace->checklist()) {
            self::explain($folder);
        }

        @touch($workspace->sessionDir());

        return true;
    }

    /**
     * Write the folder's own note, once. The dated files here are KEPT on purpose — a checklist is
     * the record of what was true at the moment it ran — and a record with no explanation beside it
     * reads as something that leaked, so the next person tidies it away.
     */
    private static function explain(string $folder): void
    {
        $notes = $folder . '/' . self::NOTES;

        if (is_file($notes)) {
            return;
        }

        File::write($notes, <<<'MD'
            # Judge checklists

            `sins.md` is the live worklist from the last `commandments judge` run: one line per sin,
            grouped under the skill that fixes it. Work it top to bottom, deleting each line as you
            fix its sin, and re-run judge only when the file is empty.

            `sins-<date>_<time>.md` are the runs before it. **They are kept deliberately** — each one
            is the record of what was true when it ran, and `commandments judge --repent=<date>_<time>`
            scopes a re-run or a `repent` to exactly what that run reported. Nothing here leaked: the
            newest five are kept and older ones are rotated out on their own.

            The whole folder is generated and gitignored. Deleting it is safe — the next judge run
            writes it again.
            MD);
    }

    /**
     * How many sins are still listed in the live worklist — each finding line (`- \`file:line\`…`) that
     * hasn't been deleted yet. Zero when the file is absent or fully worked.
     */
    public function remainingSins(): int
    {
        if (! is_file($this->path)) {
            return 0;
        }

        $count = 0;

        foreach (file($this->path) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '- `')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * A content fingerprint of the live worklist, or null when it's absent — so a nudge fires once per
     * distinct state and re-arms when a line is worked off.
     */
    public function fingerprint(): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        return md5_file($this->path) ?: null;
    }

    /**
     * Before overwriting the checklist, preserve the previous one alongside it as
     * `<name>-<when>.<ext>` (stamped with its own write time) — so a re-run never
     * clobbers the report you were working through.
     */
    public function archive(): void
    {
        if (! is_file($this->path)) {
            return;
        }

        $stamp = date('Y-m-d_His', @filemtime($this->path) ?: time());
        $archive = $this->sibling($stamp);

        // A second run within the same second would collide — keep both.
        for ($n = 2; is_file($archive); $n++) {
            $archive = $this->sibling("{$stamp}-{$n}");
        }

        @rename($this->path, $archive);
        $this->pruneArchives();
    }

    /**
     * Keep only the {@see KEEP_ARCHIVES} most-recent archives (by write time), deleting the older
     * ones — so the folder doesn't grow a checklist per run forever.
     */
    public function pruneArchives(): void
    {
        $archives = $this->archives();

        usort($archives, static fn (string $a, string $b): int => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        foreach (array_slice($archives, self::KEEP_ARCHIVES) as $old) {
            @unlink($old);
        }
    }

    /**
     * Remove the live checklist AND every archived snapshot (`sins-<stamp>.md`), then the folder
     * itself when nothing but its own note is left — an empty folder explaining files that are gone
     * is worse than no folder. Silent when there is nothing to remove.
     */
    public function clearAll(): void
    {
        @unlink($this->path);

        foreach ($this->archives() as $archive) {
            @unlink($archive);
        }

        $folder = dirname($this->path);

        // OUR folder only — never a directory the user named with `--checklist=FILE`, where the
        // note is somebody else's file. `rmdir` is the second guard: it refuses a folder that
        // still holds anything.
        if (basename($folder) === Workspace::SINS) {
            @unlink($folder . '/' . self::NOTES);
            @rmdir($folder);
        }
    }

    /**
     * Every archived snapshot beside the live worklist.
     *
     * @return list<string>
     */
    private function archives(): array
    {
        return array_values(glob($this->stem() . '-*' . $this->suffix()) ?: []);
    }

    private function sibling(string $stamp): string
    {
        return $this->stem() . '-' . $stamp . $this->suffix();
    }

    /**
     * The checklist path without its extension — what an archive's stamp is appended to.
     */
    private function stem(): string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);

        return $extension === '' ? $this->path : substr($this->path, 0, -(strlen($extension) + 1));
    }

    /**
     * The extension an archive wears, dot included, or nothing when the checklist has none.
     */
    private function suffix(): string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);

        return $extension === '' ? '' : ".{$extension}";
    }
}
