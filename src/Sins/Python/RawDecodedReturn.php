<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class RawDecodedReturn extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-raw-decoded-return',
            skill: ValueObjects::class,
            description: '`return json.loads(…)` — decoded text from outside handed on as bare dicts and lists, its shape known to no type',
            rule: 'Parse decoded data into a typed value at the boundary; never hand back a raw `json.loads(...)` result.',
            suggestion: 'Build the dataclass (or a TypedDict-typed value) from the decoded data where it arrives — `Settings.from_json(json.loads(text))`.',
        );
    }
}
