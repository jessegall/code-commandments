<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Support\Detached;

/**
 * A {@see Detached} that launches nothing and REMEMBERS what it was asked to launch. Starting a real
 * agent is the one act a test of a trigger has no business performing — and it is also the act every
 * bug in the trigger has lived inside, so the command has to be readable rather than merely absent.
 */
final class FakeDetached extends Detached
{
    /**
     * @var list<Launch> every dispatch asked of it, in order.
     */
    public array $started = [];

    public function start(string $command, string $log): void
    {
        $this->started[] = new Launch($command, $log);
    }
}
