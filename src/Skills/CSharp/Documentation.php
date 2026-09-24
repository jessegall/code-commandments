<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Documentation extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/documentation',
            tier: Tier::Mandatory,
            order: 43,
        );
    }

    public function title(): string
    {
        return 'C# documentation — short, present tense, rare';
    }

    public function trigger(): string
    {
        return "How to document C#, and mostly not to. A `/// <summary>` is a line or two about the code as it is NOW; a `//` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a `///` doc comment, a `<param>`/`<returns>` tag, or a `//` comment.";
    }

    public function intro(): string
    {
        return "A doc comment describes the code as it is, in as few words as possible. A comment is a last resort.
Neither is a changelog, a tutorial, or a story about the refactor.";
    }

    public function summary(): string
    {
        return 'short, present-tense doc comments; rare comments; never narrate the past.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Every line a reader scans costs them something, and a line that repeats the code, or tells how it came to
be, costs them and gives nothing back. Write documentation only where it tells the reader something the code
does not.

### A doc comment

One sentence in `<summary>` saying what the type or member IS or DOES, present tense, about the code as it is
now. A `<param name="order">The order.</param>` that only repeats the parameter's name and type says nothing
the signature does not; keep a tag only for what a type cannot say: a unit, a limit, which exception and when.
A summary that runs to paragraphs usually means the thing it describes does too much.

### A `//` comment

Rare. The code already says what it does; a comment earns its place by saying *why*, when the reason cannot be
read off the code: a hidden invariant, a workaround for a bug somewhere else, a constraint from outside. A
comment that repeats the line below it (`// add the rate` above `total += rate;`) is noise. Delete it.

### A `cref` points at something real

`<see cref="OrderService"/>` must name a type or member that exists. When the thing it named is renamed or
deleted, the reference is left pointing at nothing — point it at what replaced it, or remove it.

### Never the past

`// formerly lived in Checkout`, `// refactored to use the cache`, `// no longer a dictionary` describe a
version of the code nobody is reading. Git keeps the history. When you replace code, just replace it — don't
leave a comment explaining what it used to be.

### Never argue with a reader who isn't there

`// not random`, `// no magic here` defend the code against a reading nobody made. Say what it IS, or make it
obvious and write nothing.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Documentation::class => 'the same discipline for PHP docblocks.',
            FixAtTheSource::class => 'fix the shape instead of documenting the workaround.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
