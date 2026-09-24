<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class PassTheObject extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/pass-the-object',
            tier: Tier::KeepInMind,
            order: 45,
        );
    }

    public function title(): string
    {
        return 'C# pass the object — demand what you use, not an id and its container';
    }

    public function trigger(): string
    {
        return "Writing a C# method that takes an object and an id and whose first move is to look one up in the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — or one that takes a value its caller had to convert, derive or compute a bool from first. Read this before adding an `Id` parameter beside the object it keys into, and when a pass-the-object finding points here.";
    }

    public function intro(): string
    {
        return "When a method's first act is to resolve one parameter against another, the signature lies about what the
method needs. The caller resolves once, where the id was born, and passes the object the method works on.";
    }

    public function summary(): string
    {
        return 'demand the resolved object you need, not an id plus its container — the caller resolves once and passes the object (and owns the not-found failure).';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
```csharp
public void Rename(Workflow workflow, string nodeId, string title)
{
    var node = workflow.Graph.Node(nodeId);
    node.Title = title;
}
```

The method only ever wanted the node. Taking the workflow and an id means:

- **The caller already had both.** It can resolve the node itself; nothing is gained by putting it off.
- **The not-found failure lands in the wrong place.** Whoever named the id is the one who can say what a
  missing node means; buried inside `Rename`, that handling spreads to every method like it.
- **The type says nothing.** `string nodeId` where a `Node` is meant is primitive obsession.
- **It ties the method to the container's lookup** (`workflow.Graph.Node`) for no reason.

So the signature asks for what it uses — `public void Rename(Node node, string title)` — and the caller
resolves once.

### The same smell, other shapes

- **A converted argument** — every caller passes `order.Id.ToString()` or `(decimal) amount`: the method
  wants the other type; take it, or take the object and convert inside, once.
- **A derived argument** — every caller passes `order.Customer` beside `order`: the method can read it.
- **A computed bool** — every caller passes `isPaid: order.Status == OrderStatus.Paid`: hand over the order
  and let the method ask it.

### What is NOT this sin

- **A registry or repository keyed into its own store** — `handlers[kind]` is the object's job.
- **A boundary** — a controller action, a message handler or a CLI command receives ids from outside; that is
  where they are resolved.
- **A lookup whose contract is "by id"** — `FindById(id)` that hands the result straight back.
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
        return [Language::CSharp];
    }
}
