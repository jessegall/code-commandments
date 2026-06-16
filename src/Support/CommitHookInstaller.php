<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Installs (idempotently) a git pre-commit hook that blocks a commit when
 * the prophets find sins in the changed files. Warnings alone never block —
 * `judge --git` exits non-zero only for sins.
 *
 * The hook block is delimited by markers so it can be re-installed or sit
 * alongside an existing pre-commit hook without clobbering it.
 */
final class CommitHookInstaller
{
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_APPENDED = 'appended';
    public const STATUS_ALREADY_PRESENT = 'already_present';
    public const STATUS_NOT_GIT = 'not_git';
    public const STATUS_WRITE_FAILED = 'write_failed';

    private const BEGIN = '# >>> code-commandments pre-commit gate >>>';
    private const END = '# <<< code-commandments pre-commit gate <<<';

    public function install(string $basePath, bool $force = false): string
    {
        $gitDir = $basePath . '/.git';

        if (! is_dir($gitDir)) {
            return self::STATUS_NOT_GIT;
        }

        $hooksDir = $gitDir . '/hooks';

        if (! is_dir($hooksDir)) {
            @mkdir($hooksDir, 0755, true);
        }

        $hookPath = $hooksDir . '/pre-commit';
        $block = $this->block();

        if (! is_file($hookPath)) {
            if (@file_put_contents($hookPath, "#!/usr/bin/env sh\n\n" . $block . "\n") === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($hookPath, 0755);

            return self::STATUS_INSTALLED;
        }

        $existing = (string) @file_get_contents($hookPath);

        if (str_contains($existing, self::BEGIN)) {
            if (! $force) {
                return self::STATUS_ALREADY_PRESENT;
            }

            $replaced = (string) preg_replace(
                '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '/s',
                $block,
                $existing,
            );

            if (@file_put_contents($hookPath, $replaced) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($hookPath, 0755);

            return self::STATUS_INSTALLED;
        }

        // Append our block to the user's existing hook.
        if (@file_put_contents($hookPath, rtrim($existing, "\n") . "\n\n" . $block . "\n") === false) {
            return self::STATUS_WRITE_FAILED;
        }

        @chmod($hookPath, 0755);

        return self::STATUS_APPENDED;
    }

    private function block(): string
    {
        $begin = self::BEGIN;
        $end = self::END;

        return <<<HOOK
        {$begin}
        # Blocks the commit when code-commandments finds sins in changed files.
        # Run `judge --next` to walk and fix them one at a time.
        if [ -x vendor/bin/commandments ]; then
            vendor/bin/commandments judge --git
            cc_status=\$?
        elif [ -f artisan ]; then
            php artisan commandments:judge --git
            cc_status=\$?
        else
            cc_status=0
        fi

        if [ "\$cc_status" -ne 0 ]; then
            echo ""
            echo "✗ Commit blocked: the prophets found sins in your changes."
            echo "  Walk and fix them one at a time:  commandments judge --next"
            echo "  (Bypass only in a real emergency with: git commit --no-verify)"
            exit 1
        fi
        {$end}
        HOOK;
    }
}
