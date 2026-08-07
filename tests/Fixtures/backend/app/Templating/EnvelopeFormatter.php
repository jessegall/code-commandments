<?php

namespace Shop\Templating;

use JesseGall\CodeCommandments\Sins\Backend\AssembledTemplate;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Renders the plain-text envelope of a dispatch note. Joined with `PHP_EOL`
 * rather than `"\n"`, and inline rather than through a named variable — the
 * same sin wearing different clothes.
 */
final class EnvelopeFormatter
{
    #[Sinful(AssembledTemplate::class)]
    public function envelope(string $recipient, string $reference): string
    {
        return implode(PHP_EOL, [
            'DISPATCH NOTE',
            '=============',
            '',
            "To:  {$recipient}",
            "Ref: {$reference}",
            '',
            'Keep this with the goods.',
        ]);
    }
}
