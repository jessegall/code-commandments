<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Writing a file the USER owns — their instructions file, their ignore file, their settings. Plain
 * `file_put_contents` opens the target with `O_TRUNC`, so between the truncate and the last byte
 * the file on disk is short or empty; a Ctrl-C in the middle of a `composer update`, a full disk,
 * or a killed container leaves a hand-written CLAUDE.md as a fragment of ours.
 */
final class File
{
    /**
     * Write $contents to $path in one step: a temporary file beside it, then a rename, which is
     * atomic on POSIX — a reader sees the old file or the new one, never a half of either. An
     * existing file's permissions are carried over, so writing to it never quietly widens it.
     */
    public static function write(string $path, string $contents): bool
    {
        $folder = dirname($path);

        // The temporary file is written BESIDE the target, so a folder that is not there yet fails the
        // whole write — and answers false rather than throwing, which is how a caller ends up reporting
        // something it never wrote. Making it is this function's job: every caller needing the same two
        // lines is one declaration of the same fact per caller.
        if (! is_dir($folder) && ! @mkdir($folder, 0777, true) && ! is_dir($folder)) {
            return false;
        }

        $temporary = @tempnam($folder, '.cc-');

        if ($temporary === false) {
            return false;
        }

        if (@file_put_contents($temporary, $contents) !== strlen($contents)) {
            @unlink($temporary);

            return false;
        }

        // A fresh `tempnam` is 0600; match what was there, or the umask default for a new file.
        @chmod($temporary, is_file($path) ? (fileperms($path) & 0777) : (0666 & ~umask()));

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }

        clearstatcache(true, $path);

        return true;
    }
}
