<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * One running `roslyn-bridge --serve`: a request per line in, a JSON document per line out. It keeps the
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
     * The trees of the C# files under $paths — all of them, or those in $written.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $written
     * @return array<string, mixed>
     */
    public function read(array $paths, array $written = []): array
    {
        fwrite($this->input, json_encode(['paths' => $paths, 'write' => $written], JSON_UNESCAPED_SLASHES) . "\n");
        $read = json_decode((string) fgets($this->output), true);

        if (! is_array($read)) {
            throw BridgeFailed::withOutput(-1, (string) file_get_contents($this->errors));
        }

        return $read;
    }

    public function __destruct()
    {
        fclose($this->input);
        fclose($this->output);
        proc_close($this->process);
        @unlink($this->errors);
    }
}
