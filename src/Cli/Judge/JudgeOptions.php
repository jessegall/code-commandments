<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;


use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Workspace;

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
        public readonly bool $ignorePackages = false,
    ) {}

    public static function fromInput(Input $input, Workspace $workspace): self
    {
        $path = $input->firstArgument();

        // By default the findings are written to a checklist file the agent prunes
        // line-by-line, in the session's folder under the package's `.commandments/` artifact dir
        // (the whole folder is gitignored); `--no-checklist` prints only, `--checklist=FILE` retargets.
        $checklist = $input->hasFlag('no-checklist') ? null : $input->option('checklist', $workspace->path('sins.md'));

        return new self(
            path: rtrim($path ?? '.', '/'),
            skill: $input->option('skill'),
            sin: $input->option('sin'),
            list: $input->hasFlag('list'),
            exclude: $input->list('exclude'),
            checklist: $checklist,
            parallel: max(1, (int) $input->option('parallel', '8')),
            benchmark: $input->hasFlag('benchmark'),
            pathGiven: $path !== null,
            ignorePackages: $input->hasFlag('ignore-package-requirements'),
        );
    }
}
