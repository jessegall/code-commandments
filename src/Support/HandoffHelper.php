<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Ships the always-installed `handoff.sh` helper into a consumer's
 * `.claude/hooks/`. Unlike the opt-in {@see PlanLoopHookSuite}, this is wired in
 * unconditionally by `install-hooks`/`init` (and refreshed by `sync`) because a
 * handoff document is broadly useful.
 *
 * It is NOT a Claude settings hook — it is a manual helper the model runs
 * (`sh .claude/hooks/handoff.sh`) to scaffold a comprehensive `HANDOFF.md` at
 * the repo root: the mechanical snapshot is auto-gathered, the narrative is left
 * as a template for the model to complete. `HANDOFF.md` is kept out of git by
 * {@see GitignoreInstaller}.
 */
final class HandoffHelper
{
    public const SCRIPT = 'handoff.sh';

    /** The read/verify counterpart of {@see self::SCRIPT} — assembles a resume briefing. */
    public const RESUME_SCRIPT = 'resume.sh';

    /** SessionStart hook — offers a resume when a HANDOFF.md is present. */
    public const DETECT_SCRIPT = 'handoff-detect.sh';

    /**
     * The helper scripts installed together: the handoff writer, the resume
     * briefing reader, and the SessionStart detector that offers a resume.
     *
     * @var list<string>
     */
    public const SCRIPTS = [self::SCRIPT, self::RESUME_SCRIPT, self::DETECT_SCRIPT];

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_WRITE_FAILED = 'write_failed';

    /**
     * Copy the packaged helpers (handoff.sh + resume.sh) into
     * `$basePath/.claude/hooks/` (idempotent, executable).
     */
    public static function install(string $basePath): string
    {
        $target = rtrim($basePath, '/') . '/.claude/hooks';

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return self::STATUS_WRITE_FAILED;
        }

        foreach (self::SCRIPTS as $script) {
            $source = dirname(__DIR__, 2) . '/stubs/hooks/' . $script;
            $contents = @file_get_contents($source);

            if ($contents === false || @file_put_contents($target . '/' . $script, $contents) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($target . '/' . $script, 0755);
        }

        return self::STATUS_INSTALLED;
    }
}
