<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class TypeHonesty extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/type-honesty',
            tier: Tier::Mandatory,
            order: 35,
        );
    }

    public function title(): string
    {
        return 'Python type honesty — the annotation must not lie';
    }

    public function trigger(): string
    {
        return "Annotating an attribute or a parameter `X | None` that the design always has set, reading it back with `x.y if x else …` or `getattr(x, \"y\", default)`, filling a required `str` field with `\"\"` to satisfy a signature, saving `self.x` to a local and restoring it later, or a `@property` that returns a constant. Read this BEFORE you widen a type to make something pass, and when a type-honesty finding points here.";
    }

    public function intro(): string
    {
        return "An `X | None` that is never actually `None` is a lie the whole codebase pays for. Every reader has
to prove the value is there again — `if self.batch is None`, `self.batch.permits(sku) if self.batch else
False` — and one of those fallbacks answers a question for a state that cannot happen. Make the annotation
say what the design guarantees.";
    }

    public function summary(): string
    {
        return "a type must not lie: don't fake optionality with `| None` a value never is, or keep per-call scratch state on `self`.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### A certain value is typed certain

When a value is always present where it is used, the annotation says so: a required parameter, an
attribute set in `__init__` and never `None`, a field of a frozen dataclass. Hedging it as `X | None`
"because it is filled in later", or keeping per-call state on `self`, pushes the certainty back onto
every reader, who re-establishes it with defensive code. The defensive code is the symptom; the cure is
upstream, in the type.

### Not every `None` is a lie

This is the complement of `python/absence`. Absence says: model a value that is genuinely missing
honestly — `None` with a check where it is born, an empty collection, a raise. Type honesty says: do not
manufacture a missing value the design does not have. A collaborator that may legitimately be absent and
is injected once is an absence decision, not a lie.

### The tell

You are re-proving, on every read, something the design already guarantees: `x.y if x else <fake>` on
your own attribute, a fallback branch that cannot be reached, a `previous = self.x … self.x = previous`
round trip. Ask whether the value is ever actually absent here. If it is not, the annotation is lying —
move the value into the signature, a required attribute or a value object, and delete the defence.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\TypeHonesty::class => 'the same discipline over PHP.',
            Absence::class => 'the complement: absence models a genuine maybe-missing; this kills a fake one.',
            FixAtTheSource::class => 'make the type certain where the value is born, not defended at every read.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
