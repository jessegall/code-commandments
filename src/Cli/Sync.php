<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Skills\ClaudeSection;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;

/**
 * `commandments sync` — refresh the consumer's code-commandments integration so a
 * `composer update` always lands the current skills and briefing. Idempotent:
 *
 *  - publishes each teaching skill into `.claude/skills/commandments/<slug>/` — the
 *    slug is engine-prefixed (`backend/value-objects`, `frontend/vue-components`), so
 *    the whole package lives under one `commandments/` namespace dir,
 *  - injects the auto-managed "Skills — load before you work" block into CLAUDE.md
 *    (see {@see ClaudeSection}),
 *  - keeps the package's generated artifacts gitignored, and
 *  - re-wires the {@see ReminderHook} to the current event/command, so a hook change (like the
 *    move to `PostToolUse`) reaches every project on `composer update`, not only on `install`.
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
        $this->ensureConfigStub($consumer);
        ReminderHook::wire("{$consumer}/.claude/settings.json");
        $this->removeLegacyArtifacts($consumer);

        fwrite(STDOUT, "↻ code-commandments synced — {$published} skills published, CLAUDE.md briefing refreshed.\n");

        return 0;
    }

    /**
     * Drop the commented-out `.commandments/config.php` scaffold the FIRST time we sync a
     * consumer, so the config surface is discoverable in place — nothing is enabled by it. Written
     * once and never touched again ({@see ConfigFile::scaffoldIfMissing}), so a project's real
     * edits are safe; delete it if you don't want one.
     */
    private function ensureConfigStub(string $consumer): void
    {
        ConfigFile::inProject($consumer)->scaffoldIfMissing();
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
     * Everything the package GENERATES is regenerated, not hand-authored — the judge
     * checklist and any future state live under `.commandments/`, so its contents are
     * ignored. The ONE exception is `.commandments/config.php`, the project's own
     * hand-written config, which stays tracked. The published skills must sit in
     * `.claude/skills/` for the Skill tool to find them, so they're ignored there.
     * Re-asserted on every sync so consumers pick it up on `composer update`. Idempotent.
     */
    private function ensureGitignored(string $path): void
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        // Strip earlier RULE forms so config.php is the only tracked exception: a bare
        // `.commandments/` (which also hid config.php) and the earlier `!repent.php` negation.
        // Comments are left as-is — harmless, and the current block re-adds its own below.
        $stale = ['.commandments/', '!.commandments/repent.php'];
        $existing = implode("\n", array_filter(
            explode("\n", $existing),
            static fn (string $line): bool => ! in_array(trim($line), $stale, true),
        ));

        $entries = [
            // Ignore the generated artifacts (checklist + state), but NOT the config a project
            // writes by hand — `.commandments/*` + a negation, since a bare dir ignore can't be
            // un-ignored per file. `config.php` is the ONLY tracked file under the folder.
            '# code-commandments generated artifacts (checklist + state); config.php stays tracked'
                => ".commandments/*\n!.commandments/config.php",
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
            // FLAT, one level deep — Claude Code discovers `.claude/skills/<id>/SKILL.md`
            // and the directory name IS the Skill-tool invocation (`commandments-backend-absence`).
            // A nested `commandments/backend/absence/` is never found.
            $to = "{$consumer}/.claude/skills/{$skill->id()}";

            if (is_dir($from)) {
                $this->copyDir($from, $to);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear previously-published skills before republishing the current flat set: the
     * broken NESTED scheme (`.claude/skills/commandments/`) and any stale flat
     * `commandments-*` dirs (so a renamed/dropped skill doesn't linger). Published skills
     * are regenerated and gitignored, so deleting them is always safe.
     */
    private function removeLegacySkills(string $consumer): void
    {
        if (is_dir("{$consumer}/.claude/skills/commandments")) {
            $this->deleteDir("{$consumer}/.claude/skills/commandments");
        }

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
