<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Workspace;

/**
 * The judge worklist file — the session's `sins.md` — and its timestamped archives. Owns knowing where
 * they live and how to clear them, so a stale checklist from an older `judge` run never lingers as a
 * misleading reference. A checklist is disposable: it always regenerates on the next scan.
 */
final class Checklist
{
    public function __construct(private readonly string $path) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self($workspace->path('sins.md'));
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
        return is_file($this->path) ? md5_file($this->path) ?: null : null;
    }

    /**
     * Remove the live checklist AND every archived snapshot (`sins-<stamp>.md`). Silent when there is
     * nothing to remove.
     */
    public function clearAll(): void
    {
        @unlink($this->path);

        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $this->path : substr($this->path, 0, -(strlen($ext) + 1));

        foreach (glob("{$stem}-*" . ($ext === '' ? '' : ".{$ext}")) ?: [] as $archive) {
            @unlink($archive);
        }
    }
}
