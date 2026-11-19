<?php

namespace Shop\Repairs;

/**
 * Where a repair ticket stands.
 */
enum RepairState: string
{
    case Open = 'open';
    case Fixed = 'fixed';
}
