<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;

/**
 * The rules that could not run, said out loud wherever the verdict is — the console and the
 * checklist an agent works from. A rule that broke found nothing, which reads exactly like a rule
 * that found nothing wrong, and "a rule passed" is the one wrong conclusion to leave a reader with.
 */
final class SkippedRules
{
    private const string CONSEQUENCE = 'This run does NOT judge what they judge — its verdict is '
        . 'incomplete until they are fixed. Their failure was printed to STDERR when it happened.';

    /**
     * @param  list<string>  $rules  the detectors that broke, named
     */
    public function __construct(private readonly array $rules) {}

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    public function console(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return "\n\033[31m✗ {$this->headline()}\033[0m"
            . "\n\033[2m  " . self::CONSEQUENCE . "\033[0m";
    }

    public function markdown(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return "> ⚠️ **{$this->headline()}**\n> " . self::CONSEQUENCE . "\n\n";
    }

    private function headline(): string
    {
        $count = count($this->rules);
        $rules = $count === 1 ? 'rule' : 'rules';

        return "{$count} {$rules} could not run: " . implode(', ', $this->rules);
    }
}
