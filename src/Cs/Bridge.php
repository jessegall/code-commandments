<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\LocatedTool;
use JesseGall\PhpTypes\Option;

/**
 * The Roslyn bridge this package carries in `bridge/roslyn`, a prebuilt image run over C# files to read their trees.
 * .NET never runs on the host, and the bridge is never built on demand: every run goes through
 * `bridge/roslyn/roslyn-in-docker.sh`, a memory-capped container of the image `bridge/roslyn/IMAGE` names. Where there
 * is no Docker or no image, there is no bridge, and C# is not judged rather than failing the run.
 */
final class Bridge implements LocatedTool
{
    /**
     * The bridge's output format this engine reads — {@see self::read} refuses any other.
     */
    public const int VERSION = 7;

    private const string SOURCE = __DIR__ . '/../../bridge/roslyn';

    /**
     * The bridge this instance keeps running — started on the first read, reused by every read after, so whoever
     * holds the instance (the journal's hook service) reads warm.
     */
    private ?BridgeProcess $running = null;

    /**
     * The folders the running bridge's container can read.
     *
     * @var list<string>
     */
    private array $mounted = [];

    private function __construct() {}

    /**
     * The bridge — none when Docker is not running or its image is not installed.
     *
     * @return Option<self>
     */
    public static function located(): Option
    {
        $image = trim((string) file_get_contents(self::SOURCE . '/IMAGE'));
        exec('docker image inspect ' . escapeshellarg($image) . ' > /dev/null 2>&1', $output, $code);

        return $code === 0 ? Option::some(new self()) : Option::none();
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $written
     */
    public function read(array $paths, array $written = []): BridgeRead
    {
        $paths = array_map(self::resolved(...), $paths);

        return $this->process($paths)->read($paths, array_map(self::resolved(...), $written));
    }

    private static function resolved(string $path): string
    {
        return realpath($path) ?: $path;
    }

    /**
     * The running bridge, restarted with the folders $paths are in mounted when its container cannot read one.
     *
     * @param  list<string>  $paths
     */
    private function process(array $paths): BridgeProcess
    {
        $folders = array_map(static fn (string $path): string => is_dir($path) ? $path : dirname($path), $paths);
        $unread = array_filter($folders, fn (string $folder): bool => ! array_any($this->mounted, static fn (string $mount): bool => $folder === $mount || str_starts_with($folder, "{$mount}/")));

        if ($this->running === null || ! $this->running->isRunning() || $unread !== []) {
            $this->mounted = array_values(array_unique([...$this->mounted, ...$folders]));
            $this->running = BridgeProcess::start(['bash', (string) realpath(self::SOURCE . '/roslyn-in-docker.sh'), ...array_map(static fn (string $folder): string => "{$folder}:ro", $this->mounted), '--', '--serve']);
        }

        return $this->running;
    }
}
