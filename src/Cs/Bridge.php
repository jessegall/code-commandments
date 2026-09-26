<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\LocatedTool;
use JesseGall\PhpTypes\Option;

/**
 * The Roslyn bridge this package carries in `bridge/roslyn`, a prebuilt image run over C# files to read their trees.
 * .NET never runs on the host, and the bridge is never built on demand: every run goes through
 * `bridge/roslyn/roslyn-in-docker.sh`, a memory-capped container of the image `bridge/roslyn/IMAGE` names. Where there
 * is no Docker or no image, there is no bridge, and C# is not judged rather than failing the run — said out loud
 * ({@see self::missing}), never in silence.
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
        exec('docker image inspect ' . escapeshellarg(self::image()) . ' > /dev/null 2>&1', $output, $code);

        return $code === 0 ? Option::some(new self()) : Option::none();
    }

    /**
     * The image this package's bridge runs as, built once per release.
     */
    public static function image(): string
    {
        return trim((string) file_get_contents(self::SOURCE . '/IMAGE'));
    }

    /**
     * What a run without the bridge says: that C# goes unjudged, which image it needs, and how that image is built.
     */
    public static function missing(): string
    {
        $image = self::image();
        $source = (string) realpath(self::SOURCE);

        return "the C# bridge image {$image} is not available (Docker is not running, or the image is not installed), so C# is not judged; it is built once per release, never on demand: docker build -t {$image} {$source}";
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
     * The folders the bridge must read to compile $paths: each path's folder, and the folder of every project the
     * projects there reference, however deep — a project compiles with the projects it references, which may stand
     * outside the folder asked for. A folder inside another is read through it.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function mountsFor(array $paths): array
    {
        $folders = [];
        foreach ($paths as $path) {
            $folder = is_dir($path) ? $path : dirname($path);
            $folders = [...$folders, $folder, ...self::referencedFolders($folder)];
        }
        $mounts = [];
        foreach ($folders as $folder) {
            if (! array_any($folders, static fn (string $outer): bool => str_starts_with($folder, "{$outer}/")) && ! in_array($folder, $mounts, true)) {
                $mounts[] = $folder;
            }
        }

        return $mounts;
    }

    /**
     * The folder of every project the projects at $folder reference, however deep.
     *
     * @return list<string>
     */
    private static function referencedFolders(string $folder): array
    {
        $pending = self::projectsAt($folder);
        $seen = [];
        $folders = [];
        while ($pending !== []) {
            $project = array_shift($pending);
            if (isset($seen[$project])) {
                continue;
            }
            $seen[$project] = true;
            $folders[] = dirname($project);
            $pending = [...$pending, ...self::projectReferences($project)];
        }

        return $folders;
    }

    /**
     * Every project under $folder, or the one it sits inside, as the bridge finds them.
     *
     * @return list<string>
     */
    private static function projectsAt(string $folder): array
    {
        $projects = [];
        if (is_dir($folder)) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $entry): bool => ! $entry->isDir() || ! (str_starts_with($entry->getFilename(), '.') || in_array($entry->getFilename(), ['bin', 'obj', 'node_modules'], true)),
            ));
            foreach ($walk as $entry) {
                if (str_ends_with($entry->getFilename(), '.csproj')) {
                    $projects[] = $entry->getPathname();
                }
            }
        }
        $above = $folder;
        while ($projects === [] && dirname($above) !== $above) {
            $above = dirname($above);
            $projects = glob("{$above}/*.csproj") ?: [];
        }

        return $projects;
    }

    /**
     * The project files $project references, by their full paths.
     *
     * @return list<string>
     */
    private static function projectReferences(string $project): array
    {
        $xml = @simplexml_load_file($project);
        if ($xml === false) {
            return [];
        }
        $referenced = [];
        foreach ($xml->xpath('//ProjectReference[@Include]') ?: [] as $reference) {
            $path = dirname($project) . '/' . str_replace('\\', '/', (string) $reference['Include']);
            $referenced[] = self::normalised($path);
        }

        return $referenced;
    }

    /**
     * $path with its `.` and `..` steps walked, as the path they name.
     */
    private static function normalised(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return '/' . implode('/', $parts);
    }

    /**
     * The running bridge, restarted with the folders $paths are in mounted when its container cannot read one.
     *
     * @param  list<string>  $paths
     */
    private function process(array $paths): BridgeProcess
    {
        $folders = self::mountsFor($paths);
        $unread = array_filter($folders, fn (string $folder): bool => ! array_any($this->mounted, static fn (string $mount): bool => $folder === $mount || str_starts_with($folder, "{$mount}/")));

        if ($this->running === null || ! $this->running->isRunning() || $unread !== []) {
            $this->mounted = array_values(array_unique([...$this->mounted, ...$folders]));
            $this->running = BridgeProcess::start(['bash', (string) realpath(self::SOURCE . '/roslyn-in-docker.sh'), ...array_map(static fn (string $folder): string => "{$folder}:ro", $this->mounted), '--', '--serve']);
        }

        return $this->running;
    }
}
