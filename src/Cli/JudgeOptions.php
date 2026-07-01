<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The parsed options of a `judge` run — every flag EXCEPT the scope flags
 * (`--changes`/`--branch`), which {@see Scope\Scope::fromArgs} owns.
 */
final class JudgeOptions
{
    /**
     * @param  list<string>  $exclude
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $skill,
        public readonly ?string $sin,
        public readonly bool $list,
        public readonly array $exclude,
        public readonly ?string $checklist,
        public readonly int $parallel,
        public readonly bool $benchmark,
        public readonly bool $pathGiven,
    ) {}

    public static function fromArgs(array $args): self
    {
        $path = '.';
        $pathGiven = false;
        $skill = null;
        $sin = null;
        $list = false;
        $parallel = 8;
        $benchmark = false;
        $exclude = [];

        // By default the findings are written to a checklist file the agent prunes
        // line-by-line, under the package's `.commandments/` artifact folder (the
        // whole folder is gitignored); `--no-checklist` prints only, `--checklist=FILE` retargets.
        $checklist = '.commandments/sins.md';

        foreach ($args as $arg) {
            if ($arg === '--list') {
                $list = true;
            } elseif ($arg === '--benchmark') {
                $benchmark = true;
            } elseif (str_starts_with($arg, '--parallel=')) {
                $parallel = max(1, (int) substr($arg, 11));
            } elseif ($arg === '--no-checklist') {
                $checklist = null;
            } elseif (str_starts_with($arg, '--checklist=')) {
                $checklist = substr($arg, 12);
            } elseif (str_starts_with($arg, '--skill=')) {
                $skill = substr($arg, 8);
            } elseif (str_starts_with($arg, '--sin=')) {
                $sin = substr($arg, 6);
            } elseif (str_starts_with($arg, '--exclude=')) {
                $exclude = array_values(array_filter(explode(',', substr($arg, 10))));
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
                $pathGiven = true;
            }
        }

        return new self(rtrim($path, '/'), $skill, $sin, $list, $exclude, $checklist, $parallel, $benchmark, $pathGiven);
    }
}
