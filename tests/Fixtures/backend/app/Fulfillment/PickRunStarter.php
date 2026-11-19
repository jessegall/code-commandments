<?php

namespace Shop\Fulfillment;

use Shop\Models\Picker;

/**
 * Starts a picker on a lane, once through the column array and once through the typed admission.
 */
final class PickRunStarter
{
    public function start(Picker $picker, string $lane): PickRun
    {
        return PickRun::admit($picker, ['lane' => $lane, 'priority' => 2]);
    }

    public function startAdmitted(Picker $picker, string $lane): PickRun
    {
        return PickRun::admitted($picker, new PickAdmission($lane, 2));
    }
}
