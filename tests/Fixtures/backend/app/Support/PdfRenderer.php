<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\BloatedDocblock;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Wraps the PDF engine. Loads fonts, registers the company letterhead, sets the
 * page geometry and the colour profile, then exposes a render entry point that
 * accepts raw HTML.
 *
 * Note that the underlying library is not thread-safe, so a single instance must
 * never be shared across queued jobs — construct a fresh one per job.
 */
#[Sinful(BloatedDocblock::class)]
final class PdfRenderer
{
    /** @var list<string> */
    private array $fonts = [];

    public function __construct(private readonly string $letterhead) {}

    public function registerFont(string $path): void
    {
        $this->fonts[] = $path;
    }

    public function render(string $html): string
    {
        $document = $this->letterhead . implode('', $this->fonts) . $html;

        return base64_encode($document);
    }
}
