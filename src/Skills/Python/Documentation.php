<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Documentation extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/documentation',
            tier: Tier::Mandatory,
            order: 42,
        );
    }

    public function title(): string
    {
        return 'Python documentation — concise, present-tense, rare';
    }

    public function trigger(): string
    {
        return "How to document Python, and mostly not to. A docstring is a line or two about the code as it is NOW; a `#` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a docstring, a `#` comment, or an `Args:`/`Returns:` section.";
    }

    public function intro(): string
    {
        return "A docstring describes the code as it is, in as few words as possible. A comment is a last resort. Neither
is a changelog, a tutorial, or a story about the refactor.";
    }

    public function summary(): string
    {
        return 'concise, present-tense docstrings; rare comments; never narrate the past.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
Every line a reader scans is a cost, and a line that restates the code, or tells how it came to be, is cost
with nothing back. Write documentation only where it tells the reader something the code does not.

### A docstring

One sentence saying what the module, class or function IS or DOES, present tense, about the code as it is
now. An `Args:`/`Returns:` block that only repeats the annotations (`name (str): the name`) says nothing the
signature does not; keep a section only for what a type cannot say: a unit, a constraint, which exception
and when. A docstring that runs to paragraphs usually means the thing it describes does too much.

### A `#` comment

Rare. The code already says what it does; a comment earns its place by saying *why*, when the reason cannot be
read off the code: a hidden invariant, a workaround for an outside bug, a constraint from elsewhere. A comment
that repeats the line below it (`# add the rate` above `total += rate`) is noise. Delete it.

### Never the past

`# formerly lived in checkout`, `# refactored to use the cache`, `# no longer a dict` describe a version of the
code nobody is reading. Git holds the history. When you replace code, just replace it — don't leave a comment explaining what it used to be.

### Never a strawman

`# not random`, `# no magic here` defend the code against a reading nobody made. State what it IS, or make it
self-evident and write nothing.
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
        return [Language::Python];
    }
}
