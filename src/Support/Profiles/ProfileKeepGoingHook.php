<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Ships the `profile-keep-going.sh` Stop hook into a consumer's `.claude/hooks/`.
 * Wired into settings.json only for a profile whose {@see ProfileOptions::$keepGoing}
 * is true; the script self-resolves the active profile at run time, so one copy
 * serves every profile.
 */
final class ProfileKeepGoingHook
{
    public const SCRIPT = 'profile-keep-going.sh';

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_WRITE_FAILED = 'write_failed';

    public static function install(string $basePath): string
    {
        $target = rtrim($basePath, '/') . '/.claude/hooks';

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return self::STATUS_WRITE_FAILED;
        }

        $source = dirname(__DIR__, 3) . '/stubs/hooks/' . self::SCRIPT;
        $contents = @file_get_contents($source);

        if ($contents === false || @file_put_contents($target . '/' . self::SCRIPT, $contents) === false) {
            return self::STATUS_WRITE_FAILED;
        }

        @chmod($target . '/' . self::SCRIPT, 0755);

        return self::STATUS_INSTALLED;
    }
}
