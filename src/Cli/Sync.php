<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Skills\ClaudeSection;
use JesseGall\CodeCommandments\Skills\Skills;

/**
 * `commandments sync` — refresh the consumer's code-commandments integration so a
 * `composer update` always lands the current skills and briefing. Idempotent:
 *
 *  - publishes each teaching skill into `.claude/skills/commandments/<slug>/` — the
 *    slug is engine-prefixed (`backend/value-objects`, `frontend/vue-components`), so
 *    the whole package lives under one `commandments/` namespace dir,
 *  - injects the auto-managed "Skills — load before you work" block into CLAUDE.md
 *    (see {@see ClaudeSection}), and
 *  - keeps the package's generated artifacts gitignored.
 *
 * Runs in the consumer's working directory (where `composer update` runs).
 */
final class Sync
{
    public function run(array $args): int
    {
        $consumer = getcwd();
        $packageSkills = dirname(__DIR__, 2) . '/skills/commandments';

        $published = $this->publishSkills($packageSkills, $consumer);
        $this->injectClaudeSection($consumer);
        $this->ensureGitignored("{$consumer}/.gitignore");
        $this->removeLegacyArtifacts($consumer);

        fwrite(STDOUT, "↻ code-commandments synced — {$published} skills published, CLAUDE.md briefing refreshed.\n");

        return 0;
    }

    /**
     * Delete artifacts the package wrote at their OLD locations, now that generated
     * files live under `.commandments/`. Runs on every sync so a consumer migrates
     * automatically on `composer update`. The files are generated/gitignored, so
     * removing them is always safe.
     */
    private function removeLegacyArtifacts(string $consumer): void
    {
        foreach (['commandments-sins.md'] as $legacy) {
            $path = "{$consumer}/{$legacy}";

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Everything the package generates is regenerated, not hand-authored — the judge
     * checklist and any future state live under `.commandments/` (one ignored
     * folder); the published skills must sit in `.claude/skills/` for the Skill tool
     * to find them, so they're ignored there. Re-asserted on every sync so consumers
     * pick it up on `composer update`. Idempotent.
     */
    private function ensureGitignored(string $path): void
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $entries = [
            '# code-commandments generated artifacts (judge checklist + state)' => '.commandments/',
            '# code-commandments published skills (regenerated on composer update)' => '.claude/skills/commandments/',
        ];

        foreach ($entries as $comment => $entry) {
            if (str_contains($existing, $entry)) {
                continue;
            }

            $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';
            $existing .= $prefix . "\n{$comment}\n{$entry}\n";
        }

        if ($existing !== '') {
            file_put_contents($path, $existing);
        }
    }

    private function publishSkills(string $source, string $consumer): int
    {
        $this->removeLegacySkills($consumer);

        $count = 0;

        foreach (Skills::all() as $skill) {
            $from = "{$source}/{$skill->slug}";
            $to = "{$consumer}/.claude/skills/commandments/{$skill->slug}";

            if (is_dir($from)) {
                $this->copyDir($from, $to);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove skills published under the OLD flat scheme (`.claude/skills/commandments-<slug>/`)
     * now that they live nested under `.claude/skills/commandments/`. Published skills
     * are regenerated and gitignored, so deleting them is always safe; the hyphen-glob
     * never matches the new `commandments/` dir (no hyphen).
     */
    private function removeLegacySkills(string $consumer): void
    {
        foreach (glob("{$consumer}/.claude/skills/commandments-*", GLOB_ONLYDIR) ?: [] as $stale) {
            $this->deleteDir($stale);
        }
    }

    private function injectClaudeSection(string $consumer): void
    {
        $path = "{$consumer}/CLAUDE.md";
        $block = ClaudeSection::render();
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        $begin = strpos($existing, ClaudeSection::BEGIN);
        $end = strpos($existing, ClaudeSection::END);

        if ($begin !== false && $end !== false) {
            $updated = substr($existing, 0, $begin) . $block . substr($existing, $end + strlen(ClaudeSection::END));
        } elseif ($existing === '') {
            $updated = "# CLAUDE.md\n\n{$block}\n";
        } else {
            // Insert the block right after the first heading/line.
            $split = strpos($existing, "\n");
            $head = $split === false ? $existing : substr($existing, 0, $split + 1);
            $rest = $split === false ? '' : substr($existing, $split + 1);
            $updated = $head . "\n" . $block . "\n" . $rest;
        }

        if ($updated !== $existing) {
            file_put_contents($path, $updated);
        }
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        /** @var \SplFileInfo $item */
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    private function copyDir(string $from, string $to): void
    {
        if (! is_dir($to) && ! mkdir($to, 0775, true) && ! is_dir($to)) {
            return;
        }

        /** @var \SplFileInfo $item */
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        ) as $item) {
            $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);

            if ($item->isDir()) {
                @mkdir($target, 0775, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}
