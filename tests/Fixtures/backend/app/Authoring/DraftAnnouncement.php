<?php

namespace Shop\Authoring;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A positional construction fills the same promise with the same nothing. The argument list gives no
 * hint which slot it lands in, which is what makes the positional form the easier one to get wrong.
 */
final class DraftAnnouncement
{
    private const string UNTITLED = 'Untitled workflow';

    #[Sinful(PlaceholderFilledData::class)]
    public function draft(string $slug): WorkflowRowData
    {
        $name = $slug === '' ? self::UNTITLED : $slug;

        return new WorkflowRowData($slug, $name, null, false, '');
    }

    /** Righteous: the timestamp is real, so the envelope's promise holds. */
    #[Righteous(PlaceholderFilledData::class)]
    public function publish(string $slug, string $name, string $stamp): WorkflowRowData
    {
        return new WorkflowRowData($slug, $name, null, true, $stamp);
    }
}
