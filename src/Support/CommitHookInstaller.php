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

    private const PUSH_GATE_BEGIN = '# >>> code-commandments pre-push gate >>>';
    private const PUSH_GATE_END = '# <<< code-commandments pre-push gate <<<';

    /** Block ids — the unit a profile installs/strips. @see self::blockSpecs() */
    public const BLOCK_PRE_COMMIT_GATE = 'pre-commit-gate';
    public const BLOCK_PRE_PUSH_GATE = 'pre-push-gate';
    public const BLOCK_POST_COMMIT_RESET = 'post-commit-reset';
    public const BLOCK_PRE_PUSH_RESET = 'pre-push-reset';
    public const BLOCK_COMMIT_MSG_GUARD = 'commit-msg-guard';

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

    /**
     * The grind end-gate: blocks the PUSH on sins across the whole branch
     * (`judge --branch` — changed since the branch base, so it survives the
     * intermediate phase commits, unlike `--git`/`--staged`). Warnings are still
     * printed but do not block (a branch-scoped judge gates sins only).
     */
    private function prePushGateBlock(): string
    {
        $begin = self::PUSH_GATE_BEGIN;
        $end = self::PUSH_GATE_END;

        return <<<HOOK
        {$begin}
        # Blocks the push when code-commandments finds sins in the active profile's
        # scope (grind: the branch; penance: the whole codebase). A bare `judge`
        # resolves that scope from the profile. Warnings are shown, not blocked.
        if [ -x vendor/bin/commandments ]; then
            vendor/bin/commandments judge --no-cache
            cc_status=\$?
        elif [ -f artisan ]; then
            php artisan commandments:judge --no-cache
            cc_status=\$?
        else
            cc_status=0
        fi

        if [ "\$cc_status" -ne 0 ]; then
            echo ""
            echo "✗ Push blocked: unresolved sins remain in scope."
            echo "  Reckon before pushing:  commandments judge --next"
            echo "  (Bypass only in a real emergency with: git push --no-verify)"
            exit 1
        fi
        {$end}
        HOOK;
    }

    /**
     * The spec for every owned block: id => [hook, begin marker, end marker,
     * body method]. Declaration order is the CANONICAL in-file order — so when a
     * file holds two owned blocks (pre-push: gate then reset), the gate is written
     * first and runs before the reset (judge while until-push absolutions are
     * still live, then clear them).
     *
     * @return array<string, array{0: GitHook, 1: string, 2: string, 3: string}>
     */
    private function blockSpecs(): array
    {
        return [
            self::BLOCK_PRE_COMMIT_GATE => [GitHook::PreCommit, self::BEGIN, self::END, 'block'],
            self::BLOCK_PRE_PUSH_GATE => [GitHook::PrePush, self::PUSH_GATE_BEGIN, self::PUSH_GATE_END, 'prePushGateBlock'],
            self::BLOCK_PRE_PUSH_RESET => [GitHook::PrePush, self::PUSH_BEGIN, self::PUSH_END, 'prePushBlock'],
            self::BLOCK_POST_COMMIT_RESET => [GitHook::PostCommit, self::POST_BEGIN, self::POST_END, 'postCommitBlock'],
            self::BLOCK_COMMIT_MSG_GUARD => [GitHook::CommitMsg, self::MSG_BEGIN, self::MSG_END, 'commitMsgBlock'],
        ];
    }

    /**
     * Reconcile the git hooks to EXACTLY the given set of owned block ids — every
     * desired block is (re)written in canonical order, every owned block not in the
     * set is stripped, and a hook file left with no owned block and nothing but a
     * shebang is removed. Foreign hook content (the consumer's own, or another
     * tool's) is always preserved. This is the single profile-driven entry point;
     * teardown is computed from what is ACTUALLY ON DISK, not a remembered profile.
     *
     * @param  list<string>  $desiredBlockIds
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public function applyBlocks(string $basePath, array $desiredBlockIds, callable $emit, callable $error): void
    {
        $gitDir = rtrim($basePath, '/') . '/.git';

        if (! is_dir($gitDir)) {
            if ($desiredBlockIds !== []) {
                $error('Not a git repository — skipped the commit hooks.');
            }

            return;
        }

        if ($this->hooksPathRedirected($basePath) && $this->setHasBlockingGate($desiredBlockIds)) {
            $error('git core.hooksPath is redirected away from .git/hooks (husky or similar) — the commandments gate will NOT fire. Wire `commandments judge` into your hook manager manually.');
        }

        $specs = $this->blockSpecs();
        $desired = array_values(array_filter($desiredBlockIds, static fn (string $id): bool => isset($specs[$id])));

        // Group desired blocks per hook file, preserving canonical (spec) order.
        $byFile = [];
        foreach ($specs as $id => $spec) {
            if (in_array($id, $desired, true)) {
                $byFile[$spec[0]->value][] = $id;
            }
        }

        // Every file that currently holds an owned block must also be visited, so a
        // block no longer desired is stripped (and the file possibly removed).
        $onDiskFiles = [];
        foreach ($this->installedBlocks($basePath) as $id) {
            $onDiskFiles[$specs[$id][0]->value] = true;
        }

        $files = array_values(array_unique([...array_keys($byFile), ...array_keys($onDiskFiles)]));

        foreach ($files as $hookValue) {
            $this->composeHookFile($basePath, GitHook::from($hookValue), $byFile[$hookValue] ?? [], $emit, $error);
        }
    }

    /**
     * The owned block ids currently present on disk (any of our marker pairs found
     * in a hook file), in canonical order.
     *
     * @return list<string>
     */
    public function installedBlocks(string $basePath): array
    {
        $hooksDir = rtrim($basePath, '/') . '/.git/hooks';
        $present = [];

        foreach ($this->blockSpecs() as $id => $spec) {
            $path = $hooksDir . '/' . $spec[0]->value;

            if (is_file($path) && str_contains((string) @file_get_contents($path), $spec[1])) {
                $present[] = $id;
            }
        }

        return $present;
    }

    /**
     * Write a hook file to hold exactly $blockIds (in canonical order) plus any
     * foreign content, or remove it when nothing of ours and no foreign body remain.
     *
     * @param  list<string>  $blockIds
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    private function composeHookFile(string $basePath, GitHook $hook, array $blockIds, callable $emit, callable $error): void
    {
        $specs = $this->blockSpecs();
        $hooksDir = rtrim($basePath, '/') . '/.git/hooks';

        if (! is_dir($hooksDir)) {
            @mkdir($hooksDir, 0755, true);
        }

        $path = $hooksDir . '/' . $hook->value;
        $existing = is_file($path) ? (string) @file_get_contents($path) : T_String::empty();

        $foreign = $this->stripOwnedBlocks($existing);
        $foreignBody = trim(preg_replace('/^#!.*$/m', T_String::empty(), $foreign) ?? T_String::empty());

        // Order the desired blocks canonically (spec order).
        $ordered = [];
        foreach (array_keys($specs) as $id) {
            if (in_array($id, $blockIds, true)) {
                $ordered[] = $id;
            }
        }

        if ($ordered === []) {
            if ($foreignBody === '') {
                if (is_file($path)) {
                    @unlink($path);
                    $emit("Removed git {$hook->value} hook (no commandments blocks remain)");
                }

                return;
            }

            // Foreign content remains — write it back with our blocks stripped.
            if (@file_put_contents($path, rtrim($foreign, T_String::NEWLINE) . T_String::NEWLINE) === false) {
                $error("Failed to write .git/hooks/{$hook->value} — check permissions.");

                return;
            }

            @chmod($path, 0755);
            $emit("Stripped commandments block(s) from your existing .git/hooks/{$hook->value}");

            return;
        }

        $blocks = [];
        foreach ($ordered as $id) {
            $method = $specs[$id][3];
            $blocks[] = $this->{$method}();
        }
        $blocksText = implode(T_String::PARAGRAPH, $blocks);

        $new = $foreignBody === ''
            ? "#!/usr/bin/env sh\n\n" . $blocksText . T_String::NEWLINE
            : rtrim($foreign, T_String::NEWLINE) . T_String::PARAGRAPH . $blocksText . T_String::NEWLINE;

        if ($new === $existing) {
            // Already exactly right — stay quiet (keeps `sync` from churning).
            return;
        }

        if (@file_put_contents($path, $new) === false) {
            $error("Failed to write .git/hooks/{$hook->value} — check permissions.");

            return;
        }

        @chmod($path, 0755);
        $emit("Wrote git {$hook->value} hook (" . implode(', ', $ordered) . ')');
    }

    /**
     * Remove every owned code-commandments block (any marker pair) from $content,
     * leaving foreign content (and the shebang) intact.
     */
    private function stripOwnedBlocks(string $content): string
    {
        foreach ($this->allMarkerPairs() as [$begin, $end]) {
            $content = (string) preg_replace_callback(
                '/\n*' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\n*/s',
                static fn (): string => T_String::NEWLINE,
                $content,
            );
        }

        return $content;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function allMarkerPairs(): array
    {
        $pairs = [];

        foreach ($this->blockSpecs() as $spec) {
            $pairs[] = [$spec[1], $spec[2]];
        }

        return $pairs;
    }

    /**
     * @param  list<string>  $blockIds
     */
    private function setHasBlockingGate(array $blockIds): bool
    {
        return in_array(self::BLOCK_PRE_COMMIT_GATE, $blockIds, true)
            || in_array(self::BLOCK_PRE_PUSH_GATE, $blockIds, true);
    }

    /**
     * Whether git hooks are redirected away from `.git/hooks` (e.g. husky sets
     * core.hooksPath), in which case our gate would be written to a dead file.
     */
    private function hooksPathRedirected(string $basePath): bool
    {
        $hooksPath = trim((string) @shell_exec(
            'git -C ' . escapeshellarg($basePath) . ' config --get core.hooksPath 2>/dev/null',
        ));

        if ($hooksPath === '') {
            return false;
        }

        $resolved = realpath($hooksPath) ?: realpath(rtrim($basePath, '/') . '/' . $hooksPath);
        $default = realpath(rtrim($basePath, '/') . '/.git/hooks');

        return $resolved !== $default;
    }
}
