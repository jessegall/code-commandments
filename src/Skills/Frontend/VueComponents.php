<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Frontend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class VueComponents extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'frontend/vue-components',
            tier: Tier::KeepInMind,
            order: 15,
        );
    }

    public function title(): string
    {
        return "Vue components — extract repetition and deep reaches";
    }

    public function description(): string
    {
        return "Extract a component when template markup REPEATS identically, or when an element in a large template reaches DEEP into nested data (data.user.firstName). Repeated markup is one component waiting to be born; a deep reach is a child that knows too much about the data shape and wants the mid-object as a prop. Read this BEFORE copy-pasting a block of template or reaching `a.b.c` in a sizeable component.";
    }

    public function intro(): string
    {
        return "Two forms of one rule: **a chunk of template that repeats, or that reaches deep into
data, is a component trying to get out.** Pull it into its own file, pass it what it
needs as props, and use it by name.";
    }

    public function summary(): string
    {
        return "extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A template earns a new component the moment a chunk of it **repeats**, **reaches deep into nested data**, or
**nests far past readable** — each is the same signal that one coherent thing is trapped inside a bigger one
and wants to be lifted out, named, and given props.

When an element binds or interpolates a chain three-or-more levels deep (`order.customer.fullName`), that is
the Law of Demeter showing up in the markup: the element knows the shape of an object two hops away. Lift it
into a component that takes the **mid-object** as a prop, so it reaches one level, not three —
`<OrderCustomer :customer="order.customer" />` depends only on the slice it renders, not on `order`'s whole
shape.

When the DOM nests past readability with a whole sub-tree still below, don't extract a random node
mid-chain: look back **up** to the natural boundary — the top of the wrapper stack, or the `<li>` of a list
— and lift THAT. Name it for what it is: `{Item}List` / `{Item}ListItem` for a list, `{Object}Section` for a
panel, the compound's purpose (`PairReaderDialog`) for an inline primitive. The point is always one coherent
unit out, props in.
PRINCIPLE;
    }


    public function related(): array
    {
        return [
            VueControlFlow::class => "the other half of an honest template — dispatch with `<SwitchCase>`, don't re-test a subject with `v-if` chains.",
        ];
    }
}
