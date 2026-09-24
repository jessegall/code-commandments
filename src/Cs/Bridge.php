<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\BuiltTool;
use JesseGall\CodeCommandments\Support\LocatedTool;
use JesseGall\PhpTypes\Option;

/**
 * The Roslyn bridge this package carries in `bridge/roslyn`, built once per version of its sources and
 * run over C# files to read their trees. It needs the `dotnet` SDK; where there is none, there is no
 * bridge, and C# is not judged rather than failing the run.
 */
final class Bridge implements LocatedTool
{
    /**
     * The bridge's output format this engine reads — {@see self::read} refuses any other.
     */
    public const int VERSION = 7;

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
     */
    public static function located(): Option
    {
        $dotnet = trim((string) shell_exec('command -v dotnet 2>/dev/null'));
        $tool = new BuiltTool('roslyn-bridge', [...glob(self::SOURCE . '/*.cs') ?: [], ...glob(self::SOURCE . '/*.csproj') ?: []], 'roslyn-bridge.dll');

        if ($dotnet === '' || ! $tool->isBuiltBy(static fn (string $into) => self::build($dotnet, $into))) {
            return Option::none();
        }

        return Option::some(new self($dotnet, "{$tool->folder()}/roslyn-bridge.dll"));
    }

    /**
     * The trees of the C# files under $paths, as the bridge wrote them — every file, or only those in
     * $written while the rest still inform the types. The project is compiled whole either way.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $written
     */
    public function read(array $paths, array $written = []): BridgeRead
    {
        return $this->process()->read(array_map(self::resolved(...), $paths), array_map(self::resolved(...), $written));
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
     * Build the bridge into $into with `dotnet build`, starting no build server: a compiler server or
     * MSBuild node outlives the build by minutes and inherits every descriptor the caller left open, so
     * whatever reads the caller's output through a pipe would wait for it long after the build is done.
     */
    private static function build(string $dotnet, string $into): bool
    {
        $project = realpath(self::SOURCE . '/Roslyn.Bridge.csproj');
        exec(escapeshellarg($dotnet) . ' build ' . escapeshellarg((string) $project) . ' -c Release --nologo -v quiet --disable-build-servers -o ' . escapeshellarg($into) . ' 2>&1', $output, $code);

        return $code === 0;
    }
}
