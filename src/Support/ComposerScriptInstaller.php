<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Idempotently registers a command in a composer.json `scripts` block.
 *
 * Reads composer.json, appends `$command` to the given script event
 * (creating the event array and the `scripts` key if either is missing),
 * writes back with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`. Returns
 * a status so the caller can report what changed.
 */
final class ComposerScriptInstaller
{
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_ALREADY_PRESENT = 'already_present';
    public const STATUS_MISSING_FILE = 'missing_file';
    public const STATUS_INVALID_JSON = 'invalid_json';
    public const STATUS_WRITE_FAILED = 'write_failed';

    /**
     * @return self::STATUS_*
     */
    public function install(string $composerJsonPath, string $event, string $command): string
    {
        if (! is_file($composerJsonPath)) {
            return self::STATUS_MISSING_FILE;
        }

        $raw = @file_get_contents($composerJsonPath);

        if ($raw === false) {
            return self::STATUS_MISSING_FILE;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return self::STATUS_INVALID_JSON;
        }

        $scripts = $data['scripts'] ?? [];

        if (! is_array($scripts)) {
            return self::STATUS_INVALID_JSON;
        }

        $existing = $scripts[$event] ?? [];

        // Composer allows either a single string or a list of strings for
        // a script event. Normalise to a list to simplify de-duplication.
        if (is_string($existing)) {
            $existing = [$existing];
        }

        if (! is_array($existing)) {
            return self::STATUS_INVALID_JSON;
        }

        if (in_array($command, $existing, true)) {
            return self::STATUS_ALREADY_PRESENT;
        }

        $existing[] = $command;
        $scripts[$event] = $existing;
        $data['scripts'] = $scripts;

        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if ($encoded === false) {
            return self::STATUS_WRITE_FAILED;
        }

        if (@file_put_contents($composerJsonPath, $encoded . "\n") === false) {
            return self::STATUS_WRITE_FAILED;
        }

        return self::STATUS_INSTALLED;
    }
}
