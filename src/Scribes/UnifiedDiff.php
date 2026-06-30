<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * Renders a rewriter's `path => newContent` map as a unified diff against the files'
 * on-disk content — the "render" half of a rewrite (mirrors how SinReport renders a
 * detector run), used for `--dry-run`. No files are written.
 */
final class UnifiedDiff
{
    /**
     * @param  array<string, string>  $rewrites  path => new content
     */
    public function of(array $rewrites, string $base): string
    {
        $diff = '';

        foreach ($rewrites as $path => $content) {
            $diff .= $this->one($path, $content, $base);
        }

        return $diff;
    }

    /**
     * A unified diff of one file's on-disk content vs its rewritten content, labelled
     * with the real (relative) path.
     */
    private function one(string $path, string $newContent, string $base): string
    {
        $old = (string) tempnam(sys_get_temp_dir(), 'cc-old-');
        $new = (string) tempnam(sys_get_temp_dir(), 'cc-new-');
        file_put_contents($old, (string) @file_get_contents($path));
        file_put_contents($new, $newContent);

        $raw = (string) @shell_exec('diff -u ' . escapeshellarg($old) . ' ' . escapeshellarg($new) . ' 2>/dev/null');

        @unlink($old);
        @unlink($new);

        $relative = str_starts_with($path, $base . '/') ? substr($path, strlen($base) + 1) : $path;
        $raw = (string) preg_replace('/^--- .*$/m', "--- a/{$relative}", $raw, 1);

        return (string) preg_replace('/^\+\+\+ .*$/m', "+++ b/{$relative}", $raw, 1);
    }
}
