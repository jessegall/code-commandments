<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\TernaryStatement;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Records a heartbeat. Written in the SHORT form, where the missing arm makes the disguise
 * plainest: `$label ?: $this->warn()` reads as a value with a fallback, but the fallback is a
 * side effect and the value goes nowhere.
 */
final class HeartbeatLog
{
    /**
     * @var array<int, string>
     */
    private array $beats = [];

    #[Sinful(TernaryStatement::class)]
    public function beat(string $label): void
    {
        $label ?: $this->warnUnlabelled();

        $this->beats[] = $label;
    }

    /**
     * @return array<int, string>
     */
    public function since(int $offset): array
    {
        return array_slice($this->beats, $offset);
    }

    private function warnUnlabelled(): void {}
}
