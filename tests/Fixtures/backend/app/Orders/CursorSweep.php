<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\ScratchStateRestore;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Parks the sweep cursor on `$this`, walks a range, then rewinds the field to
 * where it started. The starting position is an argument; holding it as mutable
 * field state is the reason the save and restore exist.
 */
final class CursorSweep
{
    private int $cursor = 0;

    #[Sinful(ScratchStateRestore::class)]
    public function sweep(int $start, int $end): int
    {
        $saved = $this->cursor;
        $this->cursor = $start;

        $seen = 0;
        while ($this->cursor < $end) {
            $this->cursor++;
            $seen++;
        }

        $this->cursor = $saved;

        return $seen;
    }
}
