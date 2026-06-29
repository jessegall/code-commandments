<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Runs the detectors over a parsed codebase and returns lightweight findings —
 * everything the report needs and nothing that holds an AST node, so a finding can
 * cross a process boundary.
 *
 * Detectors run in parallel: after the (single) parse, a pool of up to N workers —
 * capped at the CPU core count — each runs a slice of the detectors over the
 * copy-on-write-shared AST and ships its findings back over a socket pair. The
 * parent merges them. `--parallel=1`, or a build without `pcntl`/socket pairs,
 * takes the sequential path.
 */
final class DetectorRunner
{
    public function __construct(private readonly int $parallel) {}

    /**
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    public function run(array $detectors, Codebase $codebase, ProgressBar $progress): array
    {
        $workers = min(max(1, $this->parallel), $this->cpuCount());

        if ($workers === 1 || ! $this->canFork()) {
            return $this->runSequential($detectors, $codebase, $progress);
        }

        return $this->runForked($detectors, $codebase, $workers, $progress);
    }

    /**
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    private function runSequential(array $detectors, Codebase $codebase, ProgressBar $progress): array
    {
        $progress->start(count($detectors));
        $findings = [];

        foreach ($detectors as $detector) {
            $progress->advance($this->shortName($detector));

            foreach ($this->collectFindings([$detector], $codebase) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Fork up to $workers children, each running one slice of the detectors and
     * shipping its serialized findings back over a socket. A chunk that can't be
     * forked (pair/fork failure) runs inline in the parent instead, so a partial
     * failure degrades rather than double-runs or aborts.
     *
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    private function runForked(array $detectors, Codebase $codebase, int $workers, ProgressBar $progress): array
    {
        $chunks = array_chunk($detectors, (int) ceil(count($detectors) / $workers));
        $progress->start(count($detectors));

        $children = [];
        $findings = [];

        foreach ($chunks as $chunk) {
            $pair = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = $pair === false ? -1 : pcntl_fork();

            if ($pid === -1) {
                if ($pair !== false) {
                    fclose($pair[0]);
                    fclose($pair[1]);
                }

                foreach ($this->collectFindings($chunk, $codebase) as $finding) {
                    $findings[] = $finding;
                }

                $this->advanceBy($progress, count($chunk));

                continue;
            }

            if ($pid === 0) {
                fclose($pair[0]);
                fwrite($pair[1], serialize($this->collectFindings($chunk, $codebase)));
                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[$pid] = ['sock' => $pair[0], 'count' => count($chunk)];
        }

        // Drain in COMPLETION order, not fork order: select on all sockets and read
        // whoever's ready, so a slow worker doesn't stall the progress bar behind it
        // while later-finished workers wait their turn. Sockets are non-blocking; a
        // worker's payload is buffered until its socket reaches EOF (it closed).
        $buffers = [];

        foreach ($children as $pid => $child) {
            stream_set_blocking($child['sock'], false);
            $buffers[$pid] = '';
        }

        while ($children !== []) {
            $read = array_column($children, 'sock');
            $write = $except = null;

            if (@stream_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($read as $sock) {
                $pid = $this->pidOf($children, $sock);

                if ($pid === null) {
                    continue;
                }

                $chunk = fread($sock, 65536);

                if (is_string($chunk) && $chunk !== '') {
                    $buffers[$pid] .= $chunk;
                }

                if (! feof($sock)) {
                    continue;
                }

                fclose($sock);
                pcntl_waitpid($pid, $status);

                $partial = $buffers[$pid] !== '' ? unserialize($buffers[$pid]) : [];

                if (is_array($partial)) {
                    foreach ($partial as $finding) {
                        $findings[] = $finding;
                    }
                }

                $this->advanceBy($progress, $children[$pid]['count']);
                unset($children[$pid], $buffers[$pid]);
            }
        }

        // Reap anything left if select bailed out (rare).
        foreach (array_keys($children) as $pid) {
            @fclose($children[$pid]['sock']);
            pcntl_waitpid($pid, $status);
        }

        return $findings;
    }

    /**
     * The pid whose socket is $sock — `stream_select` re-keys the array it's handed,
     * so look the worker up by socket identity.
     *
     * @param  array<int, array{sock: resource, count: int}>  $children
     * @param  resource  $sock
     */
    private function pidOf(array $children, $sock): ?int
    {
        foreach ($children as $pid => $child) {
            if ($child['sock'] === $sock) {
                return $pid;
            }
        }

        return null;
    }

    /**
     * Run a set of detectors and flatten their matches into lightweight,
     * serializable findings (no AST node survives — only the strings the report
     * needs).
     *
     * @param  list<Detector>  $detectors
     * @return list<Finding>
     */
    private function collectFindings(array $detectors, Codebase $codebase): array
    {
        $findings = [];

        foreach ($detectors as $detector) {
            $short = $this->shortName($detector);
            $skill = $detector->skill();

            foreach ($detector->find($codebase) as $match) {
                $findings[] = new Finding($short, $skill, $match->file->path, $match->location(), $match->scope());
            }
        }

        return $findings;
    }

    private function advanceBy(ProgressBar $progress, int $steps): void
    {
        for ($i = 0; $i < $steps; $i++) {
            $progress->advance();
        }
    }

    /**
     * The number of CPU cores — the hard cap on worker count. Falls back to 1 when
     * it can't be determined (forking then effectively off).
     */
    private function cpuCount(): int
    {
        foreach (['nproc 2>/dev/null', 'sysctl -n hw.ncpu 2>/dev/null'] as $command) {
            $value = trim((string) @shell_exec($command));

            if ($value !== '' && ctype_digit($value)) {
                return max(1, (int) $value);
            }
        }

        return 1;
    }

    /**
     * Is process forking available on this build? (`pcntl`/socket pairs — absent on
     * Windows and some hardened CLIs, where judge runs sequentially.)
     */
    private function canFork(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('stream_socket_pair');
    }

    private function shortName(Detector $detector): string
    {
        $parts = explode('\\', $detector::class);

        return end($parts);
    }
}
