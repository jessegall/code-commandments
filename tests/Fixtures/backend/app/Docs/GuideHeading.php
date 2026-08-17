<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Text\BlankText;

/**
 * Reads the heading a guide page shows in the sidebar. A page with no heading of its own returns a Null
 * Object into a `string` return type, which renders it to `''` before the caller ever sees an object.
 */
final class GuideHeading
{
    /**
     * @param  array<string, string>  $frontMatter
     */
    public function __construct(private readonly array $frontMatter) {}

    #[Sinful(ErasedNullObject::class)]
    public function sidebar(): string
    {
        $declared = $this->frontMatter['sidebar'] ?? null;

        if ($declared === null) {
            return new BlankText;
        }

        return ucfirst($declared);
    }
}
