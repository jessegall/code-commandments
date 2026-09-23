<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ValueObjects extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/value-objects',
            tier: Tier::Mandatory,
            order: 32,
        );
    }

    public function title(): string
    {
        return 'Python value objects — give related data a type';
    }

    public function trigger(): string
    {
        return "Passing or returning a `dict` whose string keys are a fixed record (`{\"sku\": …, \"quantity\": …}`), reading `row[\"field\"]` or `payload.get(\"field\")` on data your own code built, or adding a third parameter that always travels with two others. Read this BEFORE you shape data as a dict or grow a signature — the answer is usually a frozen dataclass.";
    }

    public function intro(): string
    {
        return "A dict with string keys is a record nobody declared. Every reader re-learns its shape from
the code that happened to build it, a typo in a key is a `KeyError` at run time instead of an
error in the editor, and nothing says which keys are always there.";
    }

    public function summary(): string
    {
        return 'give related data a type — a frozen dataclass — instead of a dict with string keys passed around, or values that always travel together.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### A record is a type

When the keys of a dict are known in advance — the same handful, read by name — the dict is a record,
and a record is a type:

```python
from dataclasses import dataclass


@dataclass(frozen=True)
class Line:
    sku: str
    quantity: int
    unit_price: int
```

The fields are declared once, the type checker sees every read, a missing field fails where the value
is built, and `frozen=True` means nobody changes it behind your back. Behaviour that reads only those
fields — a total, a label — becomes a method on it.

### Build it at the edge

Loose data arrives as dicts: JSON, a form, a row. Turn it into the type **where it enters** — one
`Line.from_payload(payload)` — and pass the type from there on. The rest of the program never sees
the dict, so it never has to wonder which keys are there.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that.

### What is NOT this sin

- A dict used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up by a
  value you did not write in the source.
- `**kwargs` forwarded unchanged, and the dict a serializer hands you right before you convert it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\ValueObjects::class => 'the same discipline on the PHP backend.',
            \JesseGall\CodeCommandments\Skills\Python\Absence::class => 'a field that is always there is typed as such, not read with `.get(...) or ""`.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
