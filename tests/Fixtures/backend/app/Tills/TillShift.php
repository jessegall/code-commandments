<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The span of time a till is staffed: when it opened and when it closes, stretched as cashiers join.
 */
final class TillShift
{
    private int $opensAt = 0;

    private int $closesAt = 0;

    public function __construct(private readonly string $till) {}

    public function open(int $opensAt, int $closesAt): void
    {
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
    }

    #[Sinful(CoupledFields::class)]
    public function stretch(int $from, int $until): void
    {
        if ($from < $this->opensAt || $until > $this->closesAt) {
            $this->opensAt = min($from, $this->opensAt);
            $this->closesAt = max($until, $this->closesAt);
        }
    }

    public function label(): string
    {
        return "{$this->till}: {$this->opensAt}-{$this->closesAt}";
    }
}
