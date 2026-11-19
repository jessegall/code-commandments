<?php

namespace Shop\Repairs;

use JesseGall\CodeCommandments\Sins\Backend\GuardBesideTransition;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The desk that hands repaired items back and closes their tickets.
 */
final class RepairDesk
{
    #[Sinful(GuardBesideTransition::class)]
    public function handBack(RepairTicket $ticket): void
    {
        if ($ticket->state !== RepairState::Open) {
            throw RepairNotOpen::forTicket($ticket->number);
        }

        $ticket->close();
    }

    #[Fixed(GuardBesideTransition::class)]
    public function handOver(RepairTicket $ticket): void
    {
        $ticket->closeOpen();
    }
}
