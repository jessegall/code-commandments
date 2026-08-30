<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Agents;

use JesseGall\CodeCommandments\Support\Directory;

/**
 * Points an agent's folder at what it should read — a skill directory, or a single generated file such
 * as a published agent type. Both agents follow a symlinked
 * skill directory, so the skills are written ONCE and every agent reads the same file — a copy per
 * agent would be the same document going stale in different places.
 */
final class SkillLink
{
    /**
     * @param  bool  $symlinks  false forces the copy fallback — the only way to exercise on a machine
     *                          that has links the behaviour of a machine that doesn't.
     */
    public function __construct(private readonly bool $symlinks = true) {}

    /**
     * Make $link stand for $target. True when it does — by a link, or by a copy where links are not
     * available (Windows without developer mode, a `/mnt/c` mount, exFAT, SMB).
     */
    public function point(string $link, string $target): bool
    {
        if (! file_exists($target)) {
            return false;
        }

        if ($this->alreadyPoints($link, $target)) {
            return true;
        }

        // `symlink()` requires the parent directory to exist; nothing else here creates it.
        @mkdir(dirname($link), 0775, true);

        if (! Directory::delete($link)) {
            return false;
        }

        clearstatcache(true, $link);

        if ($this->symlinks && @symlink($this->targetFor($link, $target), $link)) {
            return true;
        }

        // Not an error worth reporting: plenty of filesystems simply have no links, and a copy reads
        // exactly the same to the agent. It only costs the single-source property.
        return is_dir($target) ? Directory::copy($target, $link) : copy($target, $link);
    }

    /**
     * Is $link already standing for $target? A LINK is compared by what it resolves to. A copy is
     * compared by its contents, because it can never resolve to the target — and without that, a
     * machine on the fallback path would delete and re-copy every skill on every install, forever.
     */
    private function alreadyPoints(string $link, string $target): bool
    {
        if (is_link($link)) {
            $resolved = realpath($link);

            return $resolved !== false && $resolved === realpath($target);
        }

        return is_dir($link) && $this->identical($link, $target);
    }

    private function identical(string $link, string $target): bool
    {
        $files = static function (string $dir): array {
            $found = [];

            /**
             * @var \SplFileInfo $file
             */
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            ) as $file) {
                $found[substr($file->getPathname(), strlen($dir) + 1)] = md5_file($file->getPathname());
            }

            ksort($found);

            return $found;
        };

        return $files($link) === $files($target);
    }

    /**
     * What to write INTO the link. Relative on POSIX, so the project stays portable — moved, mounted
     * or cloned somewhere else, the link still points at its own library.
     *
     * Windows takes an absolute path, and not by preference: its `symlink` resolves the target
     * against the PROCESS's working directory before it will create anything, rather than against
     * the link's own directory, so a relative target simply never exists and the call fails whatever
     * permissions the user has. Absolute sidesteps the resolution entirely.
     */
    private function targetFor(string $link, string $target): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return $target;
        }

        $from = explode('/', trim(dirname($link), '/'));
        $to = explode('/', trim($target, '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return implode('/', [...array_fill(0, count($from), '..'), ...$to]);
    }
}
