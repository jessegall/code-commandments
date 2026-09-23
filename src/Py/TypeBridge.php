<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Support\BuiltTool;
use JesseGall\CodeCommandments\Support\LineProcess;
use JesseGall\CodeCommandments\Support\LocatedTool;
use JesseGall\PhpTypes\Option;

/**
 * The mypy bridge this package carries in `bridge/mypy`: a Python process that types a project with mypy and
 * answers with the type of each expression by span. It needs a `python3`; mypy itself is installed, pinned,
 * into a virtual environment in the user's cache. Where either is missing there is no bridge, and Python is
 * judged untyped, as it always was, rather than failing the run.
 */
final class TypeBridge implements LocatedTool
{
    /**
     * The bridge's output format this engine reads — {@see self::read} refuses any other.
     */
    public const int VERSION = 2;

    private const string SOURCE = __DIR__ . '/../../bridge/mypy';

    /**
     * The bridge this instance keeps running — started on the first read, reused by every read after.
     */
    private ?LineProcess $running = null;

    private function __construct(private readonly string $python) {}

    public static function located(): Option
    {
        $python = trim((string) shell_exec('command -v python3 2>/dev/null'));
        $tool = new BuiltTool('mypy-bridge', [self::SOURCE . '/bridge.py', self::SOURCE . '/requirements.txt'], 'ready');

        if ($python === '' || ! $tool->isBuiltBy(static fn (string $into) => self::build($python, $into))) {
            return Option::none();
        }

        return Option::some(new self("{$tool->folder()}/venv/bin/python"));
    }

    /**
     * The types mypy resolves in the modules under $paths — for every one, or only for those in $written
     * while the rest still inform them.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $written
     */
    public function read(array $paths, array $written = []): Types
    {
        $process = $this->process();
        $process->send(['paths' => array_map(self::resolved(...), $paths), 'write' => array_map(self::resolved(...), $written)]);
        $process->expectVersion(self::VERSION);

        $byFile = [];

        while (! array_key_exists('resolution', $line = $process->line())) {
            foreach ($line['types'] as $entry) {
                $byFile[$line['path']]["{$entry['start']}:{$entry['end']}"] = Type::fromContract($entry);
            }
        }

        return new Types($byFile);
    }

    private static function resolved(string $path): string
    {
        return realpath($path) ?: $path;
    }

    private function process(): LineProcess
    {
        if ($this->running === null || ! $this->running->isRunning()) {
            $this->running = LineProcess::start('mypy bridge', [$this->python, self::SOURCE . '/bridge.py', '--serve']);
        }

        return $this->running;
    }

    /**
     * Build the bridge into $into: a virtual environment with the pinned mypy, marked ready once it is whole.
     */
    private static function build(string $python, string $into): bool
    {
        $venv = escapeshellarg("{$into}/venv");
        exec(escapeshellarg($python) . " -m venv {$venv} 2>&1 && {$venv}/bin/pip install -q -r " . escapeshellarg(self::SOURCE . '/requirements.txt') . ' 2>&1', $output, $code);

        return $code === 0 && touch("{$into}/ready");
    }
}
