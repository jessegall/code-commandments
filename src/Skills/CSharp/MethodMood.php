<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class MethodMood extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/method-mood',
            tier: Tier::Mandatory,
            order: 38,
        );
    }

    public function title(): string
    {
        return 'C# method mood — an order, or a question';
    }

    public function trigger(): string
    {
        return "Naming a C# method or property — especially one that returns `bool`, or a method that changes the object and returns `void`. Read this BEFORE you name something `Hides`, `Binds` or `Spins`, and when a method-mood finding points here.";
    }

    public function intro(): string
    {
        return "You don't describe an object to itself. A method you call is an order — `Hide()`, not `Hides()` — and
a member that answers about the object's state is a question — `IsHidden`, not `Binds()`. There are two
moods, and the call site tells you which one you are writing.";
    }

    public function summary(): string
    {
        return 'commands are orders (`Hide()`), state checks are questions (`IsHidden`, `HasParent`).';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Read the call out loud

`panel.Hide()` is an instruction you give an object: do this. `panel.Hides()` is a sentence about the
object that nobody is saying — it reads like documentation that ended up in the code. Naming a call as an
order is not a style preference; it is what a call is.

### A question for state

A member that answers instead of acting gets the other mood: a question. A `bool` about the object's own
state is `IsShown`, `HasParent`, `CanRetry` — usually a property — so `if (panel.IsShown)` reads as the
question it is. `if (panel.Shows())` reads as a claim, and the reader has to stop and work out whether the
call changes anything.

### Where the line falls

A check about a relation — one thing against another — already has a subject and an object, and the third
person is right for it: `basket.Contains(item)`, `pattern.Matches(name)`, `period.Covers(day)`. The tell is
the argument. With no argument there is nothing to compare against, so the member can only be describing
the object itself — and describing the object is what a question is for.

### What is never renamed

A name you did not choose is not a sin: an override or an interface member keeps the contract's spelling.
Only a verb the rule knows is judged, so a plural noun (`Names`, `Fields`) is never mistaken for a sentence.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\MethodMood::class => 'the same discipline in PHP.',
            ClassLayout::class => 'where a type keeps its state, next to what its members are called.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
