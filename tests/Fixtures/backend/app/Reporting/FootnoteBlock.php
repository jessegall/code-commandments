<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A footnote printed under a report table. The citation is promoted as a total `string` defaulting to
 * `''`, and every reader then has to decode that blank as "there was no citation" — twice, once to
 * decide whether to print the marker and once to decide whether to print the note.
 */
final class FootnoteBlock
{
    #[Sinful(BlankStringDefault::class)]
    public function __construct(
        public readonly int $number,
        public readonly string $body,
        public readonly string $citation = '',
    ) {}

    public function marker(): string
    {
        return $this->citation === '' ? (string) $this->number : $this->number . '†';
    }

    public function printed(): string
    {
        if (empty($this->citation)) {
            return $this->body;
        }

        return $this->body . ' (' . $this->citation . ')';
    }
}
