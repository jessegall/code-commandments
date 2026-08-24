<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The ONE way this package reads and writes a JSON file a human also edits — a project's
 * `composer.json`, its `.claude/settings.json`. Decoding, what counts as unreadable, the encode flags
 * and the trailing newline are decided here rather than re-spelled at each manifest we touch.
 */
final class JsonFile
{
    /**
     * Readable, with slashes and accents left as they were typed, so our edit to a file the user owns
     * does not rewrite every line of it.
     */
    private const int FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * What the file holds, or null when there is no readable JSON object there — no file, a BOM, a
     * stray comment, a half-written save. Null is not "empty": a caller about to overwrite must tell
     * the two apart, or a manifest its owner is midway through editing is rewritten from nothing.
     *
     * @return array<mixed>|null
     */
    public function read(): ?array
    {
        $decoded = $this->exists() ? json_decode((string) @file_get_contents($this->path), true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Write $data, creating the directory it needs. Through {@see File::write}, so nothing ever reads
     * half a manifest.
     *
     * @param  array<mixed>  $data
     */
    public function write(array $data): bool
    {
        @mkdir(dirname($this->path), 0755, true);

        return File::write($this->path, json_encode($data, self::FLAGS) . "\n");
    }
}
