<?php

namespace Shop\Audits;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * One finding of a till audit, blamed on the shift it was found in once that shift is known.
 */
final readonly class AuditFinding
{
    public function __construct(
        public ?string $shift,
        public string $message,
        public int $severity,
    ) {}

    #[Sinful(HandRolledWither::class)]
    public function blaming(?string $shift): self
    {
        if ($this->shift !== null || $shift === null) {
            return $this;
        }

        return new self($shift, $this->message, $this->severity);
    }
}
