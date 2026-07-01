<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class ValueObjects extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/value-objects',
            tier: Tier::Mandatory,
            order: 3,
        );
    }

    public function title(): string
    {
        return "Value objects — give related data a type";
    }

    public function trigger(): string
    {
        return "WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `\$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)";
    }

    public function intro(): string
    {
        return "Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a
cluster of values is passed around, returned, or reached into by string keys, it wants a name and a type.";
    }

    public function summary(): string
    {
        return "give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a cluster
of values is passed around, returned, or reached into by string keys, it wants a name and a type — the type
IS the documentation, the validation, and the contract, all enforced by the compiler instead of by every
reader's memory.

Reach for a type the moment you are about to: pass or return an `array<string, mixed>` keyed bag (its keys
are an undocumented contract — make them a type); thread three-or-more values that always travel together —
a *data clump* wearing separate parameter slots; reach into a structured array by string key
(`$entry['title']`) — a typed object that hasn't been born yet; grow an already-crowded signature (group
the related arguments into one object instead of adding the fourth); or pass a bare primitive that is really
a concept — a `string $email`, a `string $currency` + `int $amount`, a `string $key` with format rules → a
value object that owns its own validation.

Introduce the type **where the data is born** — at the boundary that first receives it, the method that
first assembles it — not three frames downstream after it has been threaded around as a bag. A value object
introduced late just relabels data everyone already mishandled. This is fix-at-the-source applied to shape.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            FixAtTheSource::class => "introduce the type where the data is born, not downstream.",
            SpatieData::class => "once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).",
            Absence::class => "the new type's fields still answer \"can this be missing?\" honestly.",
        ];
    }
}
