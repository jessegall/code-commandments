<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ClassLayout extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/class-layout',
            tier: Tier::Mandatory,
            order: 36,
        );
    }

    public function title(): string
    {
        return 'Python class layout — the inventory at the top';
    }

    public function trigger(): string
    {
        return "Adding a class attribute, a constant, or a dataclass field to a Python class — especially one that already has methods — or placing a nested class, a `ClassVar` or an annotated field. Read this BEFORE you declare state below a `def`, and when a class-layout finding points here.";
    }

    public function intro(): string
    {
        return "The head of a class is its inventory. The first screen should tell you everything the object holds —
its constants, its fields, what it is configured by — before a single `def` asks for your attention. That
only works if it is total: one attribute declared under the methods turns \"the state is up here\" into \"the
state is wherever you happen to find it\".";
    }

    public function summary(): string
    {
        return 'state at the top — constants, class attributes and fields above `__init__`, methods after.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### The order is fixed

It costs nothing to follow and nothing to remember:

1. the docstring;
2. constants — `UPPER_CASE` names and `ClassVar`s, class-level facts;
3. fields — a dataclass's annotated names, a plain class's class attributes;
4. `__init__` and `__post_init__`;
5. the methods — properties among them, since a property is behaviour that reads the fields above.

Within one group nothing is prescribed: which constant comes first is the author's business, and a tight
run of related fields should stay tight.

### The pull the other way

It is always the same, and always a mistake: a class attribute added next to the method that uses it,
because that is where the author was typing. It reads well while the whole class is in your head.
Afterwards it is a fact about the object hidden inside its behaviour, and the next reader, looking for
what the class holds, has no way to know they reached the end of the list.

If the head of the class feels too long to read, the class is holding too much: split it. Do not solve a
crowded inventory by scattering it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\ClassLayout::class => 'the same discipline over PHP classes.',
            ValueObjects::class => 'a dataclass whose fields are its inventory — read at a glance.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
