<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Skills\ClaudeSection;
use JesseGall\CodeCommandments\Skills\Skills;

/**
 * `commandments sync` — refresh the consumer's code-commandments integration so a
 * `composer update` always lands the current skills and briefing. Idempotent:
 *
 *  - publishes each teaching skill into `.claude/skills/commandments-<slug>/`, and
 *  - injects the auto-managed "Skills — load before you work" block into CLAUDE.md
 *    (see {@see ClaudeSection}).
 *
 * Runs in the consumer's working directory (where `composer update` runs).
 */
final class Sync
{
    public function run(array $args): int
    {
        $consumer = getcwd();
        $packageSkills = dirname(__DIR__, 2) . '/skills';

        $published = $this->publishSkills($packageSkills, $consumer);
        $this->injectClaudeSection($consumer);

        fwrite(STDOUT, "↻ code-commandments synced — {$published} skills published, CLAUDE.md briefing refreshed.\n");

        return 0;
    }

    private function publishSkills(string $source, string $consumer): int
    {
        $count = 0;

        foreach (Skills::all() as $skill) {
            $from = "{$source}/{$skill->slug}";
            $to = "{$consumer}/.claude/skills/commandments-{$skill->slug}";

            if (is_dir($from)) {
                $this->copyDir($from, $to);
                $count++;
            }
        }

        return $count;
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
