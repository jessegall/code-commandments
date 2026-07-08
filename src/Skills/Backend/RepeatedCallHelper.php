<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class RepeatedCallHelper extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/repeated-call-helper',
            tier: Tier::Mandatory,
            order: 16,
        );
    }

    public function title(): string
    {
        return "Promote a repeated call to a method";
    }

    public function trigger(): string
    {
        return "When you keep calling the same `with`-style (variadic, named-argument) method the same way — `\$element->copyWith(metadata: SomeData::from([...])->toArray())` at site after site — the repeated call plus its construction boilerplate is a missing method on the receiver's type. Read this when a `->with…(named: …)` / `->copyWith(named: …)` call, especially one wrapping a `Data::from([...])->toArray()`, recurs across call sites.";
    }

    public function intro(): string
    {
        return "A call you write the same way over and over is a method you haven't named yet. When the same
named argument, with the same construction boilerplate, rides a `with`-style API at site after site, give
the receiver's type the operation: one `withMetadata(\$payload)` that hides the call AND the `from(…)->toArray()`
wrap.";
    }

    public function summary(): string
    {
        return "a repeated `with`-style call passing the same named argument belongs as a named method on the receiver's type.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A variadic, named-argument method — `copyWith(mixed ...$changes)`, a `with…()` builder — is a flexible,
low-level API: it maps whatever named arguments you pass onto the object. That flexibility is the point at
ONE call site. Across many, writing the *same* named argument the *same* way each time is duplication with
a tell: you've discovered a specific operation the type should name.

### The smell

The same shape recurs at site after site:

```php
$element->copyWith(metadata: PortInputPayload::from([...])->toArray());
```

Three things repeat every time: the `copyWith(metadata: …)` call, the `PortInputPayload::from([...])` build,
and the `->toArray()` flatten — your **two arrays** (the literal you write, then the array `toArray()`
returns). The low-level `with`-call is doing a specific job that has no name.

### The fix: name the operation on the type

Give the receiver's type the method the call sites keep spelling out:

```php
public function withMetadata(PortInputPayload $payload): static
{
    return $this->copyWith(metadata: $payload->toArray());
}
```

Now every site is `$element->withMetadata($payload)` — the `copyWith` mapping and the `from(…)->toArray()`
wrap live in ONE place, named for what they do. The generic `copyWith` stays for the genuinely one-off
change; the operation you kept repeating becomes first-class.

### When it is NOT this sin

- **A one-off.** A `with`-call written once is exactly what the flexible API is for — no method needed.
- **Genuinely different arguments.** If each site passes a *different* named argument (`copyWith(label: …)`
  here, `copyWith(icon: …)` there), there is no single operation to name.
- **Unrelated types.** Two different classes that both happen to have a `copyWith` are not one operation;
  only calls that resolve to the *same* method (the same class, or one it inherits) count as repetition.
PRINCIPLE;
    }
}
