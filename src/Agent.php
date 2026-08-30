<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A worker, as the harness names one: an id that dies with its session, and a type that outlives every
 * session it ever ran in. They travel together because neither answers alone — an id says which process
 * and a type says which job. A shared primitive rather than either side's own, since the harness reports
 * it and the orchestration layer consumes it.
 */
final readonly class Agent
{
    public function __construct(
        public string $id,
        public string $type,
    ) {}

    /**
     * How this worker is ADDRESSED — the pair, because neither half answers alone. A type says which job
     * and names two different builders identically; an id says which process and means nothing tomorrow.
     * A moment that carried only the type handed `general-purpose` to four agents as their whole subject.
     */
    public function address(): string
    {
        return $this->type === '' ? $this->id : $this->type . '#' . $this->id;
    }

    /**
     * How this worker reads where somebody has to recognise it — its job where it has one, since a type
     * means something tomorrow and an id does not.
     */
    public function render(): string
    {
        return $this->type === '' ? $this->id : $this->type;
    }
}
