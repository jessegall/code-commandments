<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Installs a profile's dedicated Stop hook as the FIXED file
 * `.claude/hooks/stop-hook.sh`.
 *
 * Every profile owns its own complete Stop script (`stubs/hooks/stop/<profile>.sh`);
 * the disabled profile's is a no-op. settings.json always wires
 * `Stop -> sh .claude/hooks/stop-hook.sh` and NEVER changes — switching profiles
 * just overwrites this one file with that profile's version, so there is no
 * settings teardown and no script is ever shared between profiles.
 */
final class StopHookInstaller
{
    /** The fixed script settings.json points at, regardless of profile. */
    public const INSTALLED_NAME = 'stop-hook.sh';

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_WRITE_FAILED = 'write_failed';

    /**
     * Copy the given profile's Stop stub into `.claude/hooks/stop-hook.sh`.
     *
     * @param  string  $stub  the profile's stub basename under stubs/hooks/stop/ (e.g. 'grind.sh')
     */
    public static function install(string $basePath, string $stub): string
    {
        $target = rtrim($basePath, '/') . '/.claude/hooks';

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return self::STATUS_WRITE_FAILED;
        }

        $source = dirname(__DIR__, 3) . '/stubs/hooks/stop/' . $stub;
        $contents = @file_get_contents($source);

        if ($contents === false || @file_put_contents($target . '/' . self::INSTALLED_NAME, $contents) === false) {
            return self::STATUS_WRITE_FAILED;
        }

        @chmod($target . '/' . self::INSTALLED_NAME, 0755);

        return self::STATUS_INSTALLED;
    }
}
