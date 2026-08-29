<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * One role, held by exactly ONE agent. An agent id dies with its session, so over a long build the same
 * role is bound again and again — and a role that named two agents could not say which of them is
 * current, leaving the check that resolves a role to an agent to be answered by whichever the reader
 * happened to reach first. So a binding REPLACES the last rather than joining it.
 */
final readonly class Binding
{
    public function __construct(
        public string $agent,
        public string $role,
    ) {}

    public function isBy(string $agent): bool
    {
        return $this->agent === $agent;
    }

    public function isFor(string $role): bool
    {
        return $this->role === $role;
    }

    public function toLine(): string
    {
        return implode("\t", [$this->agent, $this->role]);
    }

    /**
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode("\t", $line, 2);

        if (count($fields) !== 2) {
            return Option::none();
        }

        [$agent, $role] = $fields;

        if ($agent === '' || $role === '') {
            return Option::none();
        }

        return Option::some(new self($agent, $role));
    }

    /**
     * How it reads in the listing — the role, then the agent named for it.
     */
    public function render(): string
    {
        return sprintf('  %-20s %s', $this->role, $this->agent);
    }
}
