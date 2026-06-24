<?php

namespace App\RunStatus;

/**
 * The presentation of a run's status: its label, badge colour, and terminality.
 */
final readonly class RunStatusBadge
{
    public function __construct(
        public string $label,
        public string $colour,
        public bool $terminal,
    ) {}
}
