<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * A tool running beside this package that answers JSON requests a line at a time — a request per line in,
 * its answer as JSON lines out — kept running between requests so it answers warm.
 */
final class LineProcess
{
    /**
     * @param  resource  $process
     * @param  resource  $input
     * @param  resource  $output
     */
    private function __construct(
        private readonly string $tool,
        private $process,
        private $input,
        private $output,
        private readonly string $errors,
    ) {}

    /**
     * $command started as $tool, the name its failures give.
     *
     * @param  list<string>  $command
     */
    public static function start(string $tool, array $command): self
    {
        $errors = (string) tempnam(sys_get_temp_dir(), 'code-commandments-tool-');
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes);

        if (! is_resource($process)) {
            throw ToolFailed::toStart($tool);
        }

        return new self($tool, $process, $pipes[0], $pipes[1], $errors);
    }

    public function isRunning(): bool
    {
        return proc_get_status($this->process)['running'];
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public function send(array $request): void
    {
        fwrite($this->input, json_encode($request, JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * The next line the tool wrote, decoded — a tool that stopped answering fails with what it said.
     *
     * @return array<string, mixed>
     */
    public function line(): array
    {
        $line = json_decode((string) fgets($this->output), true);

        if (! is_array($line)) {
            throw ToolFailed::withOutput($this->tool, -1, (string) file_get_contents($this->errors));
        }

        return $line;
    }

    /**
     * The version line that opens every answer, refused unless it is $reads.
     */
    public function expectVersion(int $reads): void
    {
        $version = $this->line()['version'] ?? null;

        if ($version !== $reads) {
            throw ToolFailed::onVersion($this->tool, $version, $reads);
        }
    }

    public function __destruct()
    {
        fclose($this->input);
        fclose($this->output);
        proc_close($this->process);
        @unlink($this->errors);
    }
}
