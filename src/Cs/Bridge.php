<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\PhpTypes\Option;

/**
 * The Roslyn bridge this package carries in `bridge/roslyn`, built once per version of its sources and
 * run over C# files to read their trees. It needs the `dotnet` SDK; where there is none, there is no
 * bridge, and C# is not judged rather than failing the run.
 */
final class Bridge
{
    /**
     * The bridge's output format this engine reads — {@see self::read} refuses any other.
     */
    public const int VERSION = 1;

    private const string SOURCE = __DIR__ . '/../../bridge/roslyn';


    /**
     * The bridge this instance keeps running — started on the first read, reused by every read after,
     * so whoever holds the instance (the journal's hook service) reads warm.
     */
    private ?BridgeProcess $running = null;

    private function __construct(
        private readonly string $dotnet,
        private readonly string $assembly,
    ) {}

    /**
     * The bridge, built if this version of it has not been yet — none when `dotnet` is not installed
     * or the build fails.
     *
     * @return Option<self>
     */
    public static function located(): Option
    {
        $dotnet = trim((string) shell_exec('command -v dotnet 2>/dev/null'));

        if ($dotnet === '') {
            return Option::none();
        }

        $built = self::cache() . '/' . self::fingerprint();
        $assembly = "{$built}/roslyn-bridge.dll";

        if (! is_file($assembly) && ! self::build($dotnet, $built)) {
            return Option::none();
        }

        return Option::some(new self($dotnet, $assembly));
    }

    /**
     * The trees of the C# files under $paths, as the bridge wrote them — every file, or only those in
     * $written while the rest still inform the types. The project is compiled whole either way.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $written
     * @return array<string, mixed>
     */
    public function read(array $paths, array $written = []): array
    {
        $read = $this->process()->read(array_map(self::resolved(...), $paths), array_map(self::resolved(...), $written));

        if (($read['version'] ?? null) !== self::VERSION) {
            throw BridgeFailed::onVersion($read['version'] ?? null);
        }

        return $read;
    }

    /**
     * $path with its symbolic links resolved, as the bridge names the files it writes — so a root and a
     * file asked for through a link (`/var` for `/private/var`) still name the same file.
     */
    private static function resolved(string $path): string
    {
        return realpath($path) ?: $path;
    }

    private function process(): BridgeProcess
    {
        if ($this->running === null || ! $this->running->isRunning()) {
            $this->running = BridgeProcess::start($this->dotnet, $this->assembly);
        }

        return $this->running;
    }

    /**
     * Where built bridges are kept: the user's cache, one folder per version of the sources.
     */
    private static function cache(): string
    {
        $cache = getenv('XDG_CACHE_HOME');

        if ($cache !== false && $cache !== '') {
            return "{$cache}/code-commandments/roslyn-bridge";
        }

        $home = getenv('HOME') ?: sys_get_temp_dir();

        return "{$home}/.cache/code-commandments/roslyn-bridge";
    }

    /**
     * What this version of the bridge's sources is — a change to any of them builds a new one.
     */
    private static function fingerprint(): string
    {
        $sources = [...glob(self::SOURCE . '/*.cs') ?: [], ...glob(self::SOURCE . '/*.csproj') ?: []];
        sort($sources);

        return substr(sha1(implode("\n", array_map(static fn (string $file): string => sha1_file($file) ?: '', $sources))), 0, 16);
    }

    /**
     * Build the bridge into $into — one process at a time, so two runs never build over each other.
     */
    private static function build(string $dotnet, string $into): bool
    {
        @mkdir(dirname($into), 0777, true);
        $lock = fopen("{$into}.lock", 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            return false;
        }

        $done = is_file("{$into}/roslyn-bridge.dll");

        if (! $done) {
            $project = realpath(self::SOURCE . '/Roslyn.Bridge.csproj');
            exec(escapeshellarg($dotnet) . ' build ' . escapeshellarg((string) $project) . ' -c Release --nologo -v quiet -o ' . escapeshellarg($into) . ' 2>&1', $output, $code);
            $done = $code === 0 && is_file("{$into}/roslyn-bridge.dll");
        }

        flock($lock, LOCK_UN);
        fclose($lock);

        return $done;
    }
}
