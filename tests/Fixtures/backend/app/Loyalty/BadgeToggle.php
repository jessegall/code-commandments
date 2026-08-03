<?php

namespace Shop\Loyalty;

use JesseGall\CodeCommandments\Sins\Backend\TernaryStatement;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Flips a member's badge. The two arms are whole ACTIONS with nothing in common but the
 * subject, and the value the ternary evaluates to is dropped — the shape says "pick a value"
 * while the code picks a branch.
 */
final class BadgeToggle
{
    #[Sinful(TernaryStatement::class)]
    public function toggle(string $member): void
    {
        $this->awarded($member) ? $this->revoke($member) : $this->award($member);
    }

    private function awarded(string $member): bool
    {
        return $member !== '';
    }

    private function award(string $member): void {}

    private function revoke(string $member): void {}
}
