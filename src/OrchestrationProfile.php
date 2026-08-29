<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Cli\Orchestration\Ruling;
use JesseGall\CodeCommandments\Cli\Orchestration\Trap;
use JesseGall\PhpTypes\Option;

/**
 * What a project declared about running several workers at once, frozen for reading. Every field is
 * optional: undeclared means the rule that needs it simply does not apply, never that a default was
 * guessed on the project's behalf.
 */
final readonly class OrchestrationProfile
{
    /**
     * @param  list<Trap>  $traps
     * @param  list<Ruling>  $rulings
     */
    public function __construct(
        private string $branch,
        private string $writer,
        public array $traps,
        public array $rulings,
        public int $running,
        public int $prefer,
    ) {}

    /**
     * The branch the work converges on, absent when the project never named one — in which case no merge
     * can be judged, because nothing says which merges matter.
     *
     * @return Option<string>
     */
    public function branch(): Option
    {
        return Option::fromTruthy($this->branch);
    }

    /**
     * The role that alone may merge, absent when nobody was named.
     *
     * @return Option<string>
     */
    public function writer(): Option
    {
        return Option::fromTruthy($this->writer);
    }

    /**
     * Is $role the one that may merge into the shared branch? False when no writer was declared — an
     * undeclared rule refuses nobody.
     */
    public function isWrittenBy(string $role): bool
    {
        return $this->writer !== '' && $this->writer === $role;
    }

    /**
     * Is a merge into $branch this project's business at all?
     */
    public function guards(string $branch): bool
    {
        return $this->branch !== '' && $this->branch === $branch;
    }
}
