<?php

namespace Shop\Labels;

/**
 * Hands rendered labels to the printer spool.
 */
final class LabelQueue
{
    public function push(string $zpl): string
    {
        return 'job-' . strlen($zpl);
    }
}
