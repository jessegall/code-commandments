<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * One running `roslyn-bridge --serve`: a request per line in, answered a file per line out. It keeps the
 * references it loaded and the trees it parsed between requests, so the second read of a project costs
 * a fraction of the first.
 */
final class BridgeProcess
{
    /**
     * @param  resource  $process
     * @param  resource  $input
     * @param  resource  $output
     */
    private function __construct(
        private $process,
        private $input,
        private $output,
        private readonly string $errors,
    ) {}

    public static function start(string $dotnet, string $assembly): self
    {
        $errors = (string) tempnam(sys_get_temp_dir(), 'roslyn-bridge-');
        $process = proc_open([$dotnet, $assembly, '--serve'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes);

        if (! is_resource($process)) {
            throw BridgeFailed::toStart();
        }

        return new self($process, $pipes[0], $pipes[1], $errors);
    }

    public function isRunning(): bool
    {
        return proc_get_status($this->process)['running'];
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
        fwrite($this->input, json_encode(['paths' => $paths, 'write' => $written], JSON_UNESCAPED_SLASHES) . "\n");

        $version = $this->line()['version'] ?? null;

        if ($version !== Bridge::VERSION) {
            throw BridgeFailed::onVersion($version);
        }

        $files = [];
        $vocabulary = new Vocabulary();

        while (! array_key_exists('resolution', $line = $this->line())) {
            $files[] = WrittenFile::fromContract($line, $vocabulary);
        }

        return new BridgeRead($files, Resolution::fromContract($line['resolution']));
    }

    /**
     * The next line the bridge wrote, decoded — a bridge that stopped answering fails with what it said.
     *
     * @return array<string, mixed>
     */
    private function line(): array
    {
        $line = json_decode((string) fgets($this->output), true);

        if (! is_array($line)) {
            throw BridgeFailed::withOutput(-1, (string) file_get_contents($this->errors));
        }

        return $line;
    }

    public function __destruct()
    {
        fclose($this->input);
        fclose($this->output);
        proc_close($this->process);
        @unlink($this->errors);
    }
}
