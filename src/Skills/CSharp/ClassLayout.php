<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ClassLayout extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/class-layout',
            tier: Tier::Mandatory,
            order: 37,
        );
    }

    public function title(): string
    {
        return 'C# class layout — what the object holds, first';
    }

    public function trigger(): string
    {
        return "Adding a field, a constant or an auto-property to a C# class, record or struct — especially one that already has methods — or deciding where a new member goes. Read this BEFORE you declare state below a method, and when a class-layout finding points here.";
    }

    public function intro(): string
    {
        return "The top of a class is a list of what the object holds. The first screen should show its constants, its
fields and its stored properties before any method asks for your attention. That only works if the list is
complete: one field declared among the methods turns \"the state is up here\" into \"the state is wherever
you happen to find it\".";
    }

    public function summary(): string
    {
        return 'state at the top — constants, fields and stored properties above the constructor, methods after.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### The order is fixed

1. constants — `const` fields and `static readonly` values, facts about the type;
2. fields;
3. stored properties — auto-properties like `{ get; init; }`, and properties given a starting value;
4. constructors;
5. everything that does something — methods, and properties computed from the state above (`=> Net + Vat`),
   which are behaviour even though they read like data.

Within one group the order is yours: which constant comes first is the author's business, and a group of
related fields should stay together.

### The pull the other way

It is always the same, and always a mistake: a field added next to the method that uses it, because that is
where you were typing. It reads fine while the whole class is in your head. Afterwards it is a fact about
the object hidden among its behaviour, and the next reader, looking for what the class holds, cannot tell
when they have reached the end of the list.

If the top of the class feels too long to read, the class holds too much: split it. Don't fix a crowded
list by scattering it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\ClassLayout::class => 'the same discipline in PHP.',
            ValueObjects::class => 'a record whose members are its list of state — read at a glance.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
