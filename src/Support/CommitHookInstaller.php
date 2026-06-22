<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Installs (idempotently) a git pre-commit hook that blocks a commit until
 * every finding on the STAGED files is resolved: sins fixed, and each warning
 * fixed OR absolved with a reason. `judge --staged` exits non-zero when any
 * sin OR un-absolved warning remains (absolved findings are filtered out).
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

    private const POST_BEGIN = '# >>> code-commandments post-commit reset >>>';
    private const POST_END = '# <<< code-commandments post-commit reset <<<';

    private const MSG_BEGIN = '# >>> code-commandments commit-msg guard >>>';
    private const MSG_END = '# <<< code-commandments commit-msg guard <<<';

    private const PUSH_BEGIN = '# >>> code-commandments pre-push reset >>>';
    private const PUSH_END = '# <<< code-commandments pre-push reset <<<';

    public function install(string $basePath, bool $force = false): string
    {
        return $this->writeHook($basePath, GitHook::PreCommit, $this->block(), self::BEGIN, self::END, $force);
    }

    /**
     * Install the post-commit hook that clears absolutions after a commit,
     * so an absolved finding never silently persists into the next phase.
     */
    public function installPostCommit(string $basePath, bool $force = false): string
    {
        return $this->writeHook($basePath, GitHook::PostCommit, $this->postCommitBlock(), self::POST_BEGIN, self::POST_END, $force);
    }

    /**
     * Install the commit-msg hook that rejects Co-authored-by trailers.
     */
    public function installCommitMsg(string $basePath, bool $force = false): string
    {
        return $this->writeHook($basePath, GitHook::CommitMsg, $this->commitMsgBlock(), self::MSG_BEGIN, self::MSG_END, $force);
    }

    /**
     * Install the pre-push hook that drops push-scoped (until-push) absolutions,
     * so a sticky LEAVE never silently outlives the grind it was scoped to.
     */
    public function installPrePush(string $basePath, bool $force = false): string
    {
        return $this->writeHook($basePath, GitHook::PrePush, $this->prePushBlock(), self::PUSH_BEGIN, self::PUSH_END, $force);
    }

    /**
     * Install all four git hooks (pre-commit gate, post-commit reset, commit-msg
     * guard, pre-push reset), emitting one status line each — the shared
     * orchestration both install-hooks and init use, so the messages can't drift.
     * Short-circuits when not a git repo.
     *
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public function installAll(string $basePath, bool $force, callable $emit, callable $error): void
    {
        match ($this->install($basePath, $force)) {
            self::STATUS_INSTALLED => $emit('Installed git pre-commit gate at .git/hooks/pre-commit'),
            self::STATUS_APPENDED => $emit('Appended the pre-commit gate to your existing .git/hooks/pre-commit'),
            self::STATUS_ALREADY_PRESENT => $emit('Pre-commit gate already installed — use --force to refresh it'),
            self::STATUS_NOT_GIT => $error('Not a git repository — skipped the commit hooks.'),
            self::STATUS_WRITE_FAILED => $error('Failed to write .git/hooks/pre-commit — check permissions.'),
        };

        if (! is_dir(rtrim($basePath, '/') . '/.git')) {
            return;
        }

        match ($this->installPostCommit($basePath, $force)) {
            self::STATUS_INSTALLED => $emit('Installed git post-commit reset at .git/hooks/post-commit'),
            self::STATUS_APPENDED => $emit('Appended the post-commit reset to your existing .git/hooks/post-commit'),
            self::STATUS_ALREADY_PRESENT => $emit('Post-commit reset already installed — use --force to refresh it'),
            self::STATUS_NOT_GIT => null,
            self::STATUS_WRITE_FAILED => $error('Failed to write .git/hooks/post-commit — check permissions.'),
        };

        match ($this->installCommitMsg($basePath, $force)) {
            self::STATUS_INSTALLED => $emit('Installed git commit-msg guard (rejects Co-authored-by) at .git/hooks/commit-msg'),
            self::STATUS_APPENDED => $emit('Appended the commit-msg guard to your existing .git/hooks/commit-msg'),
            self::STATUS_ALREADY_PRESENT => $emit('Commit-msg guard already installed — use --force to refresh it'),
            self::STATUS_NOT_GIT => null,
            self::STATUS_WRITE_FAILED => $error('Failed to write .git/hooks/commit-msg — check permissions.'),
        };

        match ($this->installPrePush($basePath, $force)) {
            self::STATUS_INSTALLED => $emit('Installed git pre-push reset (clears until-push absolutions) at .git/hooks/pre-push'),
            self::STATUS_APPENDED => $emit('Appended the pre-push reset to your existing .git/hooks/pre-push'),
            self::STATUS_ALREADY_PRESENT => $emit('Pre-push reset already installed — use --force to refresh it'),
            self::STATUS_NOT_GIT => null,
            self::STATUS_WRITE_FAILED => $error('Failed to write .git/hooks/pre-push — check permissions.'),
        };
    }

    private function writeHook(string $basePath, GitHook $hook, string $block, string $begin, string $end, bool $force): string
    {
        $gitDir = $basePath . '/.git';

        if (! is_dir($gitDir)) {
            return self::STATUS_NOT_GIT;
        }

        $hooksDir = $gitDir . '/hooks';

        if (! is_dir($hooksDir)) {
            @mkdir($hooksDir, 0755, true);
        }

        $hookPath = $hooksDir . '/' . $hook->value;

        if (! is_file($hookPath)) {
            if (@file_put_contents($hookPath, "#!/usr/bin/env sh\n\n" . $block . T_String::NEWLINE) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($hookPath, 0755);

            return self::STATUS_INSTALLED;
        }

        $existing = (string) @file_get_contents($hookPath);

        if (str_contains($existing, $begin)) {
            if (! $force) {
                return self::STATUS_ALREADY_PRESENT;
            }

            // preg_replace_callback (not preg_replace): the block is a literal
            // payload, and a plain replacement string would interpret `$1` /
            // `\1` as backreferences — silently eating the `"$1"` in the
            // commit-msg hook (no capture groups → empty), so the refreshed
            // guard greps an empty filename and stops blocking. A callback
            // returns the block verbatim.
            $replaced = (string) preg_replace_callback(
                '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '/s',
                static fn (): string => $block,
                $existing,
            );

            if (@file_put_contents($hookPath, $replaced) === false) {
                return self::STATUS_WRITE_FAILED;
            }

            @chmod($hookPath, 0755);

            return self::STATUS_INSTALLED;
        }

        // Append our block to the user's existing hook.
        if (@file_put_contents($hookPath, rtrim($existing, T_String::NEWLINE) . T_String::PARAGRAPH . $block . T_String::NEWLINE) === false) {
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
        # Blocks the commit when code-commandments finds sins in the STAGED
        # changes (only what is actually being committed). Run `judge --next`
        # to walk and fix them one at a time.
        if [ -x vendor/bin/commandments ]; then
            vendor/bin/commandments judge --staged --no-cache
            cc_status=\$?
        elif [ -f artisan ]; then
            php artisan commandments:judge --staged --no-cache
            cc_status=\$?
        else
            cc_status=0
        fi

        if [ "\$cc_status" -ne 0 ]; then
            echo ""
            echo "✗ Commit blocked: unresolved findings on your staged files."
            echo "  Every sin AND warning must be fixed, or absolved with a reason."
            echo "  Walk them one at a time:  commandments judge --next --staged"
            echo "  Fix it, or absolve:  commandments absolve --fingerprint=<hash> --reason=\"why\""
            echo "  Genuinely wrong finding (false positive / ill-fitting rule / prophet bug)?"
            echo "  Report it, do not work around it:  commandments report --prophet=NAME --reason=..."
            echo "  (Bypass only in a real emergency with: git commit --no-verify)"
            exit 1
        fi
        {$end}
        HOOK;
    }

    private function postCommitBlock(): string
    {
        $begin = self::POST_BEGIN;
        $end = self::POST_END;

        return <<<HOOK
        {$begin}
        # Clear absolutions after a commit so an absolved finding never
        # silently persists into the next phase.
        if [ -x vendor/bin/commandments ]; then
            vendor/bin/commandments absolve --clear >/dev/null 2>&1
        elif [ -f artisan ]; then
            php artisan commandments:absolve --clear >/dev/null 2>&1
        fi
        {$end}
        HOOK;
    }

    private function prePushBlock(): string
    {
        $begin = self::PUSH_BEGIN;
        $end = self::PUSH_END;

        return <<<HOOK
        {$begin}
        # Drop push-scoped (until-push) absolutions before the push lands, so a
        # sticky LEAVE never outlives the grind it was scoped to.
        if [ -x vendor/bin/commandments ]; then
            vendor/bin/commandments absolve --clear-until-push >/dev/null 2>&1
        elif [ -f artisan ]; then
            php artisan commandments:absolve --clear-until-push >/dev/null 2>&1
        fi
        {$end}
        HOOK;
    }

    private function commitMsgBlock(): string
    {
        $begin = self::MSG_BEGIN;
        $end = self::MSG_END;

        return <<<HOOK
        {$begin}
        # Reject Co-authored-by trailers in commit messages.
        if grep -qiE '^[[:space:]]*co-authored-by:' "\$1"; then
            echo ""
            echo "✗ Commit blocked: Co-authored-by trailers are not allowed."
            echo "  Remove the Co-authored-by line(s) from the commit message."
            exit 1
        fi
        {$end}
        HOOK;
    }
}
