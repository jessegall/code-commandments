<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves a country code to a shipping zone, returning an empty array for an
 * unrecognised country rather than throwing.
 */
final class ZoneResolver
{
    /**
     * @return array<int, string>
     */
    #[Sinful(MatchDefaultReturnsNull::class)]
    public function rates(string $country): array
    {
        return match ($country) {
            'NL', 'BE', 'DE' => ['eu-standard', 'eu-express'],
            'US', 'CA' => ['na-standard'],
            default => [],
        };
    }

    /**
     * A heuristic over an OPEN string set whose handled arms already admit null (they call
     * `?string` guessers) — "no suggestion" is the match's own answer vocabulary, so the
     * default gives an unrecognised carrier the same declared answer, not a swallowed
     * unhandled case (#393). Must NOT be flagged.
     */
    #[Righteous(MatchDefaultReturnsNull::class)]
    public function guessProduct(string $carrier, string $haystack): ?string
    {
        return match ($carrier) {
            'PostNL' => $this->guessPostNl($haystack),
            'DHL' => $this->guessDhl($haystack),
            default => null,
        };
    }

    private function guessPostNl(string $haystack): ?string
    {
        return $haystack === 'mailbox' ? 'postnl-letter' : null;
    }

    private function guessDhl(string $haystack): ?string
    {
        return $haystack === '' ? null : 'dhl-parcel';
    }
}
