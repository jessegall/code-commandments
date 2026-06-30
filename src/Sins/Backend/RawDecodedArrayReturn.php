<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

final class RawDecodedArrayReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'raw-decoded-array-return',
            skill: ValueObjects::class,
            description: "Returning a raw decoded boundary array (`json_decode(...)`) untyped",
            rule: "Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.",
            suggestion: "Decode into a Spatie `Data` object: `X::from(json_decode(...))`."
        );
    }
}
