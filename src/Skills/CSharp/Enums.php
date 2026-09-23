<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Enums extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/enums',
            tier: Tier::KeepInMind,
            order: 34,
        );
    }

    public function title(): string
    {
        return 'C# enums — a closed set is a type, with its knowledge on it';
    }

    public function trigger(): string
    {
        return "Modelling a value that can only be one of a handful in C# — a status, a kind, a mode held as a `string` and compared (`status == \"paid\"`), a set of `const string` fields standing in for cases, or a `switch` over string literals repeated in several classes. Read this BEFORE writing any of them.";
    }

    public function intro(): string
    {
        return "A value that can only ever be one of a few is a **closed set**, and a closed set is a type.
Held as a string, every comparison is a typo waiting to happen and every rule about the cases is
written again wherever it is needed.";
    }

    public function summary(): string
    {
        return 'a closed set of values is an `enum`, its per-case knowledge in one exhaustive `switch` expression beside it — not string constants compared at every call site.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Seal the set

A value that can only ever be one of a handful — `"paid"`, `"pending"`, `"refunded"` — is an `enum`
(serialised by name with `JsonStringEnumConverter` where it must still read as its string on the
wire):

```csharp
public enum Status { Pending, Paid, Refunded }

public static class StatusRules
{
    public static bool IsSettled(this Status status) => status switch
    {
        Status.Pending => false,
        Status.Paid or Status.Refunded => true,
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}
```

A typo is now a compile error where it is written, the compiler checks every `switch` covers every
case, and there is one place to read the whole set.

### Put the knowledge on the case

The per-case answers — a label, a colour, "is this final?" — live in one place beside the enum: an
extension method with one exhaustive `switch` expression, written with every case in view. A
comparison against string literals at a call site is that method, homeless: the next call site writes
it again, a little differently. When the cases differ in behaviour *and* data, the set is a sealed
class hierarchy, each case a type that answers for itself.

### Parse once, at the edge

A string arriving from JSON, a form or a database becomes the enum where it enters — the JSON
converter, or `Enum.Parse<Status>(raw)` — and fails there if it is not one of the cases. From then on
the code passes the enum, never the string.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour::class => 'the same discipline on the PHP backend, with backed enums.',
            \JesseGall\CodeCommandments\Skills\Python\Enums::class => 'the same discipline in Python.',
            \JesseGall\CodeCommandments\Skills\CSharp\Flow::class => 'the `else if` ladder over one subject that an enum and a `switch` expression replace.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
