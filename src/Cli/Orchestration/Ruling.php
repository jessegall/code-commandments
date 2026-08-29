<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

/**
 * A decision somebody MADE, on facts as they stood — dated, overturnable, and carrying the REASON, which is
 * the half that earns its keep: a conclusion can be re-derived, while the reason is what lets a DIFFERENT
 * reader notice the premise has stopped holding. It is RECORDED so it is stated once and travels, and never
 * APPLIED, because replaying yesterday's decision onto today's facts is worse than not having it.
 */
final readonly class Ruling
{
    /**
     * @param  ?string  $on  the day it was decided, absent when nobody recorded one
     * @param  ?string  $supersedes  the ruling it overturned, absent when it answered a new question
     */
    public function __construct(
        public string $decided,
        public string $because,
        public ?string $on = null,
        public ?string $supersedes = null,
    ) {}

    /**
     * Has this ruling been overturned by a later one? A superseded ruling is kept rather than deleted — the
     * reader needs to know a question was answered twice and why the answer changed.
     */
    public function isSuperseded(string $by): bool
    {
        return $this->decided === $by;
    }

    public function render(): string
    {
        $when = $this->on === null ? '' : " ({$this->on})";

        return "  • {$this->decided}{$when}\n    because {$this->because}";
    }
}
