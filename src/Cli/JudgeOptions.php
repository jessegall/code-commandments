<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The parsed options of a `judge` run — every flag EXCEPT the scope flags
 * (`--changes`/`--git`/`--branch`), which {@see Scope\Scope::fromArgs} owns.
 */
final class JudgeOptions
{
    /**
     * @param  list<string>  $exclude
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $skill,
        public readonly ?string $detector,
        public readonly bool $list,
        public readonly array $exclude,
        public readonly ?string $checklist,
        public readonly int $parallel,
    ) {}

    public static function fromArgs(array $args): self
    {
        $path = '.';
        $skill = null;
        $detector = null;
        $list = false;
        $parallel = 8;
        $exclude = [];

        // By default the findings are written to a checklist file the agent prunes
        // line-by-line, under the package's `.commandments/` artifact folder (the
        // whole folder is gitignored); `--no-checklist` prints only, `--checklist=FILE` retargets.
        $checklist = '.commandments/sins.md';

        foreach ($args as $arg) {
            if ($arg === '--list') {
                $list = true;
            } elseif (str_starts_with($arg, '--parallel=')) {
                $parallel = max(1, (int) substr($arg, 11));
            } elseif ($arg === '--no-checklist') {
                $checklist = null;
            } elseif (str_starts_with($arg, '--checklist=')) {
                $checklist = substr($arg, 12);
            } elseif (str_starts_with($arg, '--skill=')) {
                $skill = substr($arg, 8);
            } elseif (str_starts_with($arg, '--detector=')) {
                $detector = substr($arg, 11);
            } elseif (str_starts_with($arg, '--exclude=')) {
                $exclude = array_values(array_filter(explode(',', substr($arg, 10))));
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        return new self(rtrim($path, '/'), $skill, $detector, $list, $exclude, $checklist, $parallel);
    }
}
