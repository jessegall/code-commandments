<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ValueObjects extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/value-objects',
            tier: Tier::Mandatory,
            order: 33,
        );
    }

    public function title(): string
    {
        return 'C# value objects — give related data a type';
    }

    public function trigger(): string
    {
        return "Passing related data around in C# as a `Dictionary<string, object>` or `Dictionary<string, string>` read by fixed keys (`row[\"sku\"]`), a `JsonNode`/`JsonElement` read field by field, a tuple returned and destructured, or the same three or four parameters handed from method to method side by side. Read this BEFORE writing any of them.";
    }

    public function intro(): string
    {
        return "Data that travels together is a **thing**, not a loose pile of dictionaries and primitives.
The moment a cluster of values is passed around, returned, or read by string keys, it wants a name
and a type — the type IS the documentation, the validation and the contract, checked by the
compiler instead of by every reader's memory.";
    }

    public function summary(): string
    {
        return 'give related data a type — an immutable `record` built at the edge — instead of a dictionary read by fixed string keys, or values that always travel together.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### A record is a type

When the keys of a dictionary are known in advance — the same handful, read by name — the dictionary
is a record, and a record is a type:

```csharp
public sealed record Line(string Sku, int Quantity, long UnitPriceCents)
{
    public long Total => Quantity * UnitPriceCents;
}
```

The members are declared once, the compiler sees every read, a missing value fails where the object
is built, and a `record` cannot be changed behind your back — `with` makes the changed copy.
Behaviour that reads only those members — a total, a label — becomes a member of it.

### Build it at the edge

Loose data arrives as dictionaries and JSON: a request body, a form, a row. Turn it into the type
**where it enters** — `JsonSerializer.Deserialize<Line>(json)`, or one `Line.From(row)` factory — and
pass the type from there on. The rest of the program never sees the dictionary, so it never has to
wonder which keys are there, or cast what they hold.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that; a small one is a `readonly record struct`.
A tuple returned and taken apart by position is the same thing unnamed.

### What is NOT this sin

- A dictionary used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up
  by a value you did not write in the source.
- The dictionary a serializer or a framework hands you right before you convert it, and options
  forwarded unchanged.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\ValueObjects::class => 'the same discipline on the PHP backend, with Spatie `Data`.',
            \JesseGall\CodeCommandments\Skills\Python\ValueObjects::class => 'the same discipline in Python, with frozen dataclasses.',
            \JesseGall\CodeCommandments\Skills\CSharp\Absence::class => 'what a member of the new type holds when its value may be missing.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
