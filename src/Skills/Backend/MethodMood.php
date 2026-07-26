<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class MethodMood extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/method-mood',
            tier: Tier::Mandatory,
            order: 8,
        );
    }

    public function title(): string
    {
        return "Method mood — an order, or a question";
    }

    public function trigger(): string
    {
        return "What GRAMMAR a method name is written in: a command is an imperative (`hide()`, `openFor()`, `write()`), never a third-person narration (`hides()`, `opensFor()`, `writes()`); a method that answers `bool` about its own state wears a question (`isShown()`, `hasParent()`, `awaitsAnswer()`). Read this when you name or rename a method, and when a naming sin points here.";
    }

    public function intro(): string
    {
        return "You do not describe an object to itself. A method you CALL is an order — `hide()`, not
`hides()` — and a method that answers about state is a question — `isHidden()`, not `binds()`. Two
moods, and the call site tells you which one you are writing.";
    }

    public function summary(): string
    {
        return "commands are imperatives (`hide()`), state predicates are questions (`isHidden()`).";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Read a call out loud. `$panel->hide()` is an instruction you give an object: do this. `$panel->hides()`
is a sentence ABOUT the object, narrated by nobody, to nobody — it reads as documentation that wandered
into the code. The imperative is not a style preference; it is what a call IS. You are not describing
behaviour, you are demanding it.

The exception is a method that answers rather than acts, and it gets the other mood: a question. A
`bool` about the object's own state is `isShown()`, `hasParent()`, `canRetry()`, `awaitsAnswer()` — so
that `if ($el->isShown())` reads as the question it is. A bare `if ($el->shows())` reads as a claim, and
the reader has to stop and work out whether the call changes anything.

### Where the line falls

A predicate that asks about a RELATION — one thing against another — is already a sentence with a
subject and an object, and the third person is correct English for it: `$set->contains($item)`,
`$pattern->matches($name)`, `$range->covers($date)`. The tell is the argument: something is being
compared to something. A predicate with no argument has no second party, so it can only be describing
the receiver — and describing the receiver is what a question is for.

So:

- **acts** (returns nothing, or returns itself) → imperative: `hide()`, `enterTestMode()`, `openFor($user)`
- **answers about itself** (`bool`, no argument) → question: `isShown()`, `hasParent()`, `canRetry()`
- **answers about a relation** (`bool`, takes what it is compared with) → third person is fine:
  `contains($item)`, `matches($name)`, `covers($date)`

### What is never renamed

A name you did not choose is not a sin: a method declared by a parent class or an interface — yours or
a framework's — keeps the contract's spelling. `offsetExists()` is `ArrayAccess`'s word, not yours, and
a magic method is the language's.

### Not judged from the name alone

Only a verb the rule KNOWS is judged, so a plural-noun getter is never mistaken for narration:
`names()`, `bindings()`, `fields()` and `arguments()` are nouns, and an imperative that merely ends in
an `s` (`process()`, `pass()`, `dismiss()`, `focus()`) is left alone. When in doubt the rule says
nothing — a missed narration costs a reader a moment, a false one costs them their trust.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            RoleVocabulary::class => "what a class is called and what that name promises; this is the same contract one scale down.",
            TellDontAsk::class => "an order is the point: if you find yourself narrating what an object does, you are probably reaching into it.",
        ];
    }
}
