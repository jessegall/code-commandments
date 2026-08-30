<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

/**
 * What an agent does when a trigger fires: WHO acts and the PROCEDURE they carry out, held apart because
 * a procedure outlives the agent running it. Distinct from a {@see Binding}, which answers which agent
 * currently holds a role.
 */
final readonly class Duty
{
    public function __construct(
        public string $agent,
        public string $procedure,
    ) {}

    /**
     * One duty as a profile's settings hold it, absent when the file says something this cannot read. A
     * settings file is hand-edited, so a line it does not understand is skipped rather than fatal.
     */
    public static function fromDeclared(mixed $declared): ?self
    {
        if (! is_array($declared) || ! is_string($declared['agent'] ?? null) || ! is_string($declared['procedure'] ?? null)) {
            return null;
        }

        return new self($declared['agent'], $declared['procedure']);
    }

    /**
     * @return array{agent: string, procedure: string}
     */
    public function toDeclared(): array
    {
        return ['agent' => $this->agent, 'procedure' => $this->procedure];
    }

    public function is(string $agent, string $procedure): bool
    {
        return $this->agent === $agent && $this->procedure === $procedure;
    }

    /**
     * Does this match a partial description? An empty $agent or $procedure means "any", which is what
     * lets `off commit` drop a whole trigger and `off commit ponytail` drop only one agent's work.
     */
    public function matches(string $agent, string $procedure): bool
    {
        return ($agent === '' || $this->agent === $agent)
            && ($procedure === '' || $this->procedure === $procedure);
    }

    public function render(): string
    {
        return $this->agent . ' → ' . $this->procedure;
    }
}
