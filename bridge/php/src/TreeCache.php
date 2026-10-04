<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * A file's line as an earlier run wrote it, kept under a key of everything the line was made from: the bridge, the
 * renames, and the file's path and text, for a PHP file's tree is its own text's alone. The run's last line, the
 * symbols it reaches outside the scan, is kept under the PHP, the autoloader, the lockfile and every name the files
 * reference and declare, in order.
 */
final readonly class TreeCache
{
    /**
     * How large a line may be and still be kept: a generated file's tree is not worth the disk.
     */
    private const int KEPT = 16 << 20;

    private string $identity;

    public function __construct(private string $folder, Request $request)
    {
        if (! is_dir($folder)) {
            @mkdir($folder, 0o755, true);
        }
        $parts = [PHP_VERSION, realpath(__DIR__ . '/..') ?: __DIR__, json_encode($request->renames)];
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
     * The line kept for `$path` with `$code` as its text, where a file's context mark goes, and the names it
     * references and declares; null when none is kept for this text.
     *
     * @return array{line: string, at: int, referenced: array<string, mixed>, declared: array<string, mixed>}|null
     */
    public function kept(string $path, string $code): ?array
    {
        $stored = @file_get_contents($this->entryOf($path));
        if ($stored === false) {
            return null;
        }
        $split = strpos($stored, "\n");
        $meta = $split === false ? null : json_decode(substr($stored, 0, $split), true);
        if (! is_array($meta) || ($meta['key'] ?? null) !== $this->keyOf($path, $code)) {
            return null;
        }

        return ['line' => substr($stored, $split + 1), 'at' => $meta['at'], 'referenced' => $meta['referenced'], 'declared' => $meta['declared']];
    }

    /**
     * Keeps `$line` for `$path`, its context mark left out, with where the mark goes and the names it references and
     * declares, unless the line is too large.
     *
     * @param array<string, mixed> $referenced
     * @param array<string, mixed> $declared
     */
    public function keep(string $path, string $code, string $line, int $at, array $referenced, array $declared): void
    {
        if (strlen($line) > self::KEPT) {
            return;
        }
        $meta = ['key' => $this->keyOf($path, $code), 'at' => $at, 'referenced' => (object) $referenced, 'declared' => (object) $declared];
        $this->written($this->entryOf($path), json_encode($meta, JSON_THROW_ON_ERROR) . "\n" . $line);
    }

    /**
     * The outside-symbols line kept for the names a run reached, under `$key`; null when none is.
     */
    public function program(string $key): ?string
    {
        $stored = @file_get_contents($this->programOf($key));

        return $stored === false ? null : $stored;
    }

    /**
     * Keeps the outside-symbols line a run wrote for the names it reached.
     */
    public function keepProgram(string $key, string $line): void
    {
        $this->written($this->programOf($key), $line);
    }

    /**
     * The key of the outside symbols a run reaches: the PHP and the autoloader with its lockfile, and the names in
     * the order the run met them.
     *
     * @param array<string, mixed> $declared
     * @param array<string, mixed> $referenced
     */
    public function programKey(?string $autoload, array $declared, array $referenced): string
    {
        $lockfile = $autoload === null ? null : dirname($autoload, 2) . '/composer.lock';
        $locked = $lockfile !== null && is_file($lockfile) ? hash_file('sha256', $lockfile) : '';

        return hash('sha256', implode("\0", [$this->identity, (string) $autoload, $locked, json_encode(array_keys($declared)), json_encode(array_keys($referenced))]));
    }

    private function keyOf(string $path, string $code): string
    {
        return hash('sha256', $this->identity . "\0" . $path . "\0" . $code);
    }

    private function entryOf(string $path): string
    {
        return $this->folder . '/' . hash('sha256', $path) . '.tree';
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
