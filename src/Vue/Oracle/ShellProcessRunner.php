<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

/**
 * The production {@see ProcessRunner}: `proc_open` capturing both streams. The only place the
 * package actually shells out to `vue-tsc`.
 */
final class ShellProcessRunner implements ProcessRunner
{
    public function run(string $binary, array $arguments, string $cwd): string
    {
        $command = array_map('escapeshellarg', [$binary, ...$arguments]);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(implode(' ', $command), $descriptors, $pipes, $cwd);

        if (! is_resource($process)) {
            return '';
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ($stdout ?: '') . ($stderr ?: '');
    }
}
