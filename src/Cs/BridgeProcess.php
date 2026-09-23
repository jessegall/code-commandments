<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\LineProcess;

/**
 * One running `roslyn-bridge --serve`: a request per line in, answered a file per line out. It keeps the
 * references it loaded and the trees it parsed between requests, so the second read of a project costs
 * a fraction of the first.
 */
final class BridgeProcess
{
    private function __construct(private readonly LineProcess $process) {}

    public static function start(string $dotnet, string $assembly): self
    {
        return new self(LineProcess::start('Roslyn bridge', [$dotnet, $assembly, '--serve']));
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * The trees of the C# files under $paths — all of them, or those in $written — read a line at a time,
     * so no more than one file's tree is ever held as text.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $written
     */
    public function read(array $paths, array $written = []): BridgeRead
    {
        $this->process->send(['paths' => $paths, 'write' => $written]);
        $this->process->expectVersion(Bridge::VERSION);

        $files = [];
        $vocabulary = new Vocabulary();

        while (! array_key_exists('resolution', $line = $this->process->line())) {
            $files[] = WrittenFile::fromContract($line, $vocabulary);
        }

        return new BridgeRead($files, Resolution::fromContract($line['resolution']));
    }
}
