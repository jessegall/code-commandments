<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The ONE recursive copy and delete over a directory tree — the mechanics every publisher shares
 * ({@see \JesseGall\CodeCommandments\Cli\Sync} republishing a consumer's skills,
 * {@see \JesseGall\CodeCommandments\Workspace::prune} sweeping stale session folders). It exists
 * because a naive recursive delete is dangerous around symlinks, and both copies of it were.
 */
final class Directory
{
    /**
     * Delete $path and everything under it. Returns whether the path is now gone — a caller that
     * publishes over the result should say so rather than carry on into a half-deleted tree.
     *
     * A link is a file we REMOVE, never a door we walk through, and asking `is_link` first —
     * everywhere — is what makes that true. Three shapes each punish getting it wrong: a link as
     * the root (`is_dir` follows it, so the delete empties what the link POINTS AT and then cannot
     * remove the link), a link nested in the tree (not descended, but it reports `isDir`, and
     * `rmdir` always fails on one, so the whole parent survives half-deleted), and a dangling link
     * (neither `is_dir` nor `file_exists`, so a not-there guard skips it forever and the next
     * `symlink` fails `EEXIST`).
     */
    public static function delete(string $path): bool
    {
        // BEFORE `is_dir`, which follows a link, and before the not-there shortcut, which a
        // dangling link would otherwise slip through.
        if (is_link($path)) {
            return unlink($path);
        }

        if (! file_exists($path)) {
            return true;
        }

        if (! is_dir($path)) {
            return unlink($path);
        }

        $emptied = true;

        foreach (self::entries($path) as $entry) {
            $emptied = self::delete($entry) && $emptied;
        }

        clearstatcache(true, $path);

        return rmdir($path) && $emptied;
    }

    /**
     * Copy the tree at $from into $to, creating what it needs. Existing files are overwritten;
     * anything already in $to that $from does not have is LEFT — a merge, not a mirror, so a
     * caller that needs the target to hold only what it published deletes it first.
     */
    public static function copy(string $from, string $to): bool
    {
        if (! is_dir($from)) {
            return false;
        }

        if (! is_dir($to) && ! mkdir($to, 0775, true) && ! is_dir($to)) {
            return false;
        }

        $copied = true;

        foreach (self::entries($from) as $entry) {
            $target = $to . '/' . basename($entry);

            $copied = (is_dir($entry) && ! is_link($entry) ? self::copy($entry, $target) : copy($entry, $target)) && $copied;
        }

        return $copied;
    }

    /**
     * The direct children of $dir, as absolute paths. Deliberately one level: recursion happens in
     * the callers above, so every level gets the same is-it-a-link question — a flattening iterator
     * answers it once, at the top, which is exactly the bug.
     *
     * @return list<string>
     */
    private static function entries(string $dir): array
    {
        $entries = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $dir . '/' . $entry;
            }
        }

        return $entries;
    }
}
