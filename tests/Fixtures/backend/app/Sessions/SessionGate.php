<?php

namespace Shop\Sessions;

use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Decides whether a caller really has a session — by coalescing "no session" into the empty string
 * and then comparing against that same empty string, so "never signed in" and "signed in as
 * nobody" answer as one.
 */
final class SessionGate
{
    #[Sinful(CancelledCoalesce::class)]
    public function hasSession(?string $sessionId): bool
    {
        return ($sessionId ?? '') !== '';
    }

    #[Fixed(CancelledCoalesce::class)]
    public function hasSessionStated(?string $sessionId): bool
    {
        return $sessionId !== null && $sessionId !== '';
    }

    #[Righteous(CancelledCoalesce::class)]
    public function currencyIsBlank(?string $currency): bool
    {
        // A REAL default: the fallback is a value the domain has, not a stand-in for absence, and
        // the comparison asks something the coalesce does not answer.
        return ($currency ?? 'EUR') === '';
    }
}
