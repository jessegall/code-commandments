<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sends the dispatch manifest to the carrier.
 */
final class ManifestMailer
{
    /**
     * A `match` on a bool has exactly two arms because a bool has exactly two values — which is to
     * say this is two methods, written as one and picked at run time.
     */
    #[Sinful(FlagArgument::class)]
    public function deliver(string $manifest, bool $draft): string
    {
        return match ($draft) {
            true => 'held for review: ' . $manifest,
            false => 'sent to carrier: ' . $manifest,
        };
    }
}
