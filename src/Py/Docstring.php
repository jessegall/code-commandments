<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * What a docstring's reStructuredText says, read as text.
 */
final class Docstring
{
    /**
     * $text without its Sphinx version notes — each `.. versionadded::`, `.. versionchanged::` or
     * `.. deprecated::` directive and the lines indented under it. Those record a public API's history
     * for its users on purpose; the rest of the docstring describes the code.
     */
    public static function withoutVersionNotes(string $text): string
    {
        return (string) preg_replace('/^([ \t]*)\.\. (?:versionadded|versionchanged|deprecated)::.*(?:\n(?:\1[ \t]+.*|[ \t]*))*$/m', '', $text);
    }
}
