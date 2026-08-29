<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Orchestration;

/**
 * Where a claimed item stands. The distinction that earns its keep is between WORKING and REPORTED: one
 * is a worker computing, the other is a worker waiting on the orchestrator — and reporting the second as
 * though it were the first is how a finished piece of work sits unhanded while everyone believes it is
 * still going.
 */
enum Stage: string
{
    case Working = 'working';

    /**
     * The worker has filed a receipt and is waiting to be judged. A PERSON is waiting, which is why this
     * is surfaced above everything else.
     */
    case Reported = 'reported';

    /**
     * Waiting on an answer somebody else has to give.
     */
    case Blocked = 'blocked';

    case Accepted = 'accepted';

    /**
     * Does an item at this stage occupy one of the running slots? Only work actually being done does.
     * A reported or blocked item is waiting on the ORCHESTRATOR, and holding a slot hostage to how fast
     * it answers would be the tool charging the user for its own queue.
     */
    public function isRunning(): bool
    {
        return $this === self::Working;
    }

    /**
     * Is somebody waiting on a decision here? These are surfaced first and loudest — throughput can wait,
     * a person cannot.
     */
    public function awaitsTheOrchestrator(): bool
    {
        return $this === self::Reported || $this === self::Blocked;
    }

    /**
     * Is this item finished with — its lease released and its worker free to exit?
     */
    public function isSettled(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * What to do about an item at this stage, said as the verb that does it. A screen that names a state
     * without naming the act leaves the reader to compose it.
     */
    public function nextAct(): string
    {
        return match ($this) {
            self::Working => 'nothing — it is working',
            self::Reported => 'accept <item>, or rework <item> --because="…"',
            self::Blocked => 'answer <lane> "…"',
            self::Accepted => 'nothing — it is done',
        };
    }
}
