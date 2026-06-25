<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Writes a profile's Stop hook to the FIXED file `.claude/hooks/stop-hook.sh`,
 * GENERATING its contents from the profile's {@see ProfileBehaviour} via
 * {@see StopHookBuilder} — the package ships no per-profile Stop stub.
 *
 * settings.local.json always wires `Stop -> sh .claude/hooks/stop-hook.sh` and
 * NEVER changes between active profiles — switching profiles just overwrites this
 * one file with the regenerated script, so there is no settings teardown. A
 * profile with no Stop hook (disabled) is removed by the caller.
 */
final class StopHookInstaller
{
    /** The fixed script settings.local.json points at, regardless of profile. */
    public const INSTALLED_NAME = 'stop-hook.sh';

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_WRITE_FAILED = 'write_failed';
    public const STATUS_NONE = 'none';

    /**
     * Generate $profile's Stop hook from $opts and write it to
     * `.claude/hooks/stop-hook.sh`. Returns STATUS_NONE when the profile has no
     * Stop hook (disabled) — the caller removes any existing file.
     */
    public static function install(string $basePath, string $profile, ProfileOptions $opts): string
    {
        $script = StopHookBuilder::build($profile, $opts);

        if ($script === null) {
            return self::STATUS_NONE;
        }

        $target = rtrim($basePath, '/') . '/.claude/hooks';

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return self::STATUS_WRITE_FAILED;
        }

        if (@file_put_contents($target . '/' . self::INSTALLED_NAME, $script) === false) {
            return self::STATUS_WRITE_FAILED;
        }

        @chmod($target . '/' . self::INSTALLED_NAME, 0755);

        return self::STATUS_INSTALLED;
    }
}
