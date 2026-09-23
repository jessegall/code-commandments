<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class PassTheObject extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/pass-the-object',
            tier: Tier::KeepInMind,
            order: 44,
        );
    }

    public function title(): string
    {
        return 'Python pass the object — demand what you use, not an id and its container';
    }

    public function trigger(): string
    {
        return "Writing a Python function that takes an object and an id and whose first move is to look one up in the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — or one that takes a value its caller had to convert, derive or compute a bool from first. Read this before adding an `_id` parameter beside the object it keys into, and when a pass-the-object finding points here.";
    }

    public function intro(): string
    {
        return "When a function's first act is to resolve one parameter against another, the signature lies about what the
function needs. The caller resolves once, where the id was born, and passes the object the function works on.";
    }

    public function summary(): string
    {
        return 'demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
```python
def rename(workflow: Workflow, node_id: str, title: str) -> None:
    node = workflow.graph.node(node_id)
    node.title = title
```

The function only ever wanted the node. Taking the workflow and an id means:

- **The caller already had both.** It can resolve the node itself; nothing is gained by deferring it.
- **The not-found failure lands in the wrong place.** Whoever named the id is the one who can say what a
  missing node means; buried inside `rename`, that error handling spreads to every function like it.
- **The type says nothing.** `node_id: str` where a `Node` is meant is primitive obsession.
- **It ties the function to the container's lookup** (`workflow.graph.node`) for no reason.

So the signature demands what it uses — `def rename(node: Node, title: str)` — and the caller resolves once.

### The same smell, other shapes

- **A converted argument** — every caller passes `str(order.id)` or `Decimal(amount)`: the function wants
  the other type; take it, or take the object and convert inside, once.
- **A derived argument** — every caller passes `order.customer` beside `order`: the function can read it.
- **A computed bool** — every caller passes `is_paid=order.status == "paid"`: hand over the order and let
  the function ask it.

### What is NOT this sin

- **A registry or repository keyed into its own store** — `self._handlers[kind]` is the object's job.
- **A boundary** — a view, a CLI command or a task handler receives ids from outside; that is where they are
  resolved.
- **A lookup whose contract is "by id"** — `find_by_id(id)` that hands the result straight back.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\PassTheObject::class => 'the same discipline over PHP methods.',
            TellDontAsk::class => 'the sibling: once you hold the object, ask it rather than reaching into it.',
            ValueObjects::class => 'the type an id stands in for.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
