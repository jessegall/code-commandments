<?php

namespace Shop\Repairs;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\GuardBesideTransition;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * A customer's item in for repair, closed once the work is done.
 */
class RepairTicket extends Model
{
    protected $casts = ['state' => RepairState::class];

    public function close(): void
    {
        $this->state = RepairState::Fixed;
        $this->save();
    }

    #[Fixed(GuardBesideTransition::class)]
    public function closeOpen(): void
    {
        if ($this->state !== RepairState::Open) {
            throw RepairNotOpen::forTicket($this->number);
        }

        $this->state = RepairState::Fixed;
        $this->save();
    }
}
