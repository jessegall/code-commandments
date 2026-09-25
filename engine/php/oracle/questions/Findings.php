<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Discovery;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;

/**
 * Every backend detector's findings, unpublished ones included, each asked of the node it flags: the Go detector
 * that ports it must flag exactly these. The detectors are tuned by the scanned project's own config, as judge tunes them.
 */
final class Findings implements Question
{
    /** @var array<string, list<array{NodeMatch, string, string}>>|null each file's findings, by path */
    private ?array $found = null;

    public function name(): string
    {
        return 'findings';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $this->found ??= $this->find($codebase);
        foreach ($this->found[$file->path] ?? [] as [$finding, $detector, $sin]) {
            yield [$finding->node, $detector, $sin];
        }
    }

    /** @return array<string, list<array{NodeMatch, string, string}>> */
    private function find(Codebase $codebase): array
    {
        $detectors = $this->detectors();
        Config::load($GLOBALS['oracleRoot'])->tune($detectors);
        Bridge::publish(Bridge::gather($codebase, FrontendCodebase::scan($GLOBALS['oracleRoot'])), $detectors);
        $found = [];
        foreach ($detectors as $detector) {
            $name = substr($detector::class, strrpos($detector::class, '\\') + 1);
            foreach ($detector->find($codebase) as $finding) {
                $found[$finding->file->path][] = [$finding, $name, $detector->sin()->name()];
            }
        }

        return $found;
    }

    /** @return list<Detector> */
    private function detectors(): array
    {
        $src = dirname(__DIR__, 4) . '/src/Detectors/Backend';
        $classes = Discovery::classes($src, 'JesseGall\\CodeCommandments\\Detectors\\Backend');

        return array_values(array_map(
            static fn (string $class): Detector => new $class(),
            array_filter($classes, static fn (string $class): bool => is_subclass_of($class, Detector::class) && ! (new \ReflectionClass($class))->isAbstract()),
        ));
    }
}
