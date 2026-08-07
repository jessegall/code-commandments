<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Notes kept against a key. A key nobody wrote and a key written blank are different things, and
 * the reader below cannot tell you which it found.
 */
final class NoteBook
{
    /**
     * @var array<string, string>
     */
    private array $notes = [];

    #[Sinful(CancelledCoalesce::class)]
    public function noteOr(string $key, string $fallback): string
    {
        return ($this->notes[$key] ?? '') === '' ? $fallback : $this->notes[$key];
    }

    #[Righteous(CancelledCoalesce::class)]
    public function isUnwritten(string $key): bool
    {
        // `?? null` is not a manufactured fake — it IS the absence, so reading a key that may not be
        // there this way conflates nothing.
        return ($this->notes[$key] ?? null) === null;
    }
}
