<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * A file's line as an earlier run wrote it, its path left out, kept under a key of what the line was made from: the
 * bridge and the file's text, for a PHP file's tree is its own text's alone, so every checkout holding that text shares
 * the entry. The run's last line, the symbols it reaches outside the scan, is kept under the PHP, what the autoloader
 * installs, the lockfile and every name the files reference and declare, in order.
 */
final readonly class TreeCache
{
    /**
     * How large a line may be and still be kept: a generated file's tree is not worth the disk.
     */
    private const int KEPT = 16 << 20;

    private string $identity;

    public function __construct(private string $folder)
    {
        if (! is_dir($folder)) {
            @mkdir($folder, 0o755, true);
        }
        $parts = [PHP_VERSION];
        foreach (glob(__DIR__ . '/*.php') ?: [] as $source) {
            $parts[] = hash_file('sha256', $source);
        }
        $installed = __DIR__ . '/../parser/composer/installed.json';
        if (is_file($installed)) {
            $parts[] = hash_file('sha256', $installed);
        }
        $this->identity = hash('sha256', implode("\0", $parts));
    }

    /**
     * The line kept for `$code`, its path left out, where a file's context mark goes in it, and the names it
     * references and declares; null when none is kept for this text.
     *
     * @return array{line: string, at: int, referenced: array<string, mixed>, declared: array<string, mixed>}|null
     */
    public function kept(string $code): ?array
    {
        $entry = $this->entryOf($code);
        $stored = @file_get_contents($entry);
        if ($stored === false) {
            return null;
        }
        $split = strpos($stored, "\n");
        $meta = $split === false ? null : json_decode(substr($stored, 0, $split), true);
        if (! is_array($meta)) {
            return null;
        }
        @touch($entry);

        return ['line' => substr($stored, $split + 1), 'at' => $meta['at'], 'referenced' => $meta['referenced'], 'declared' => $meta['declared']];
    }

    /**
     * Keeps `$line` for `$code`, its path and context mark left out, with where the mark goes and the names it
     * references and declares, unless the line is too large.
     *
     * @param array<string, mixed> $referenced
     * @param array<string, mixed> $declared
     */
    public function keep(string $code, string $line, int $at, array $referenced, array $declared): void
    {
        if (strlen($line) > self::KEPT) {
            return;
        }
        $meta = ['at' => $at, 'referenced' => (object) $referenced, 'declared' => (object) $declared];
        $this->written($this->entryOf($code), json_encode($meta, JSON_THROW_ON_ERROR) . "\n" . $line);
    }

    /**
     * The outside-symbols line kept for the names a run reached, under `$key`; null when none is.
     */
    public function program(string $key): ?string
    {
        $stored = @file_get_contents($this->programOf($key));
        if ($stored === false) {
            return null;
        }
        @touch($this->programOf($key));

        return $stored;
    }

    /**
     * Keeps the outside-symbols line a run wrote for the names it reached.
     */
    public function keepProgram(string $key, string $line): void
    {
        $this->written($this->programOf($key), $line);
    }

    /**
     * The key of the outside symbols a run reaches: the PHP, what the autoloader installs with its lockfile, and the
     * names in the order the run met them.
     *
     * @param array<string, mixed> $declared
     * @param array<string, mixed> $referenced
     */
    public function programKey(?string $autoload, array $declared, array $referenced): string
    {
        $installed = $autoload === null ? [] : [dirname($autoload, 2) . '/composer.lock', dirname($autoload) . '/composer/installed.json'];
        $hashes = array_map(static fn (string $file): string => is_file($file) ? hash_file('sha256', $file) : '', $installed);

        return hash('sha256', implode("\0", [$this->identity, ...$hashes, json_encode(array_keys($declared)), json_encode(array_keys($referenced))]));
    }

    private function entryOf(string $code): string
    {
        return $this->folder . '/' . hash('sha256', $this->identity . "\0" . $code) . '.tree';
    }

    private function programOf(string $key): string
    {
        return $this->folder . '/program-' . $key . '.line';
    }

    private function written(string $file, string $content): void
    {
        $draft = $file . '.' . getmypid();
        if (@file_put_contents($draft, $content) !== false) {
            @rename($draft, $file);
        }
    }
}
