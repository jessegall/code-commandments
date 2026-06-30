<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class PassTheObject extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/pass-the-object',
            tier: Tier::KeepInMind,
            order: 13,
        );
    }

    public function title(): string
    {
        return "Pass the object, not its id";
    }

    public function description(): string
    {
        return "Demand the resolved type you need, not an id plus its container. When a method takes a container object AND a key into it, then resolves the key against the container (`request(Workflow \$workflow, string \$nodeId)` doing `\$workflow->graph->nodeById(\$nodeId)`), the lookup is misplaced — the caller passed both, so the caller already holds everything the lookup needs. Resolve at the caller and hand over the resolved OBJECT, and let the caller own the \"not found\" failure. Read this when a method signature pairs a domain object with a string/int id it then looks up inside.";
    }

    public function intro(): string
    {
        return "A method that takes `(Workflow \$workflow, string \$nodeId)` and starts with
`\$workflow->graph->nodeById(\$nodeId)` is asking for the wrong things. It needs the
**node**. The caller named the id and holds the workflow — so the caller should
resolve, and pass the resolved object.";
    }

    public function summary(): string
    {
        return "demand the resolved type you need, not an id plus its container: a method that takes `(Workflow \$workflow, string \$nodeId)` then unpacks `\$workflow->graph->nodeById(\$nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
When a method's first act is to **resolve one parameter against another** — key a
scalar id into a container object to get the thing it actually works on — the
signature is lying about what the method needs, and the resolution is in the wrong
place. Push it up: the **caller** resolves and passes the resolved object; the method
**demands the type it uses**.

Why it's the caller's job:

- **The caller already holds everything.** It passed both the container and the id,
  so it can call the lookup itself — nothing is gained by deferring it inward.
- **The "not found" failure belongs to whoever named the id.** Burying
  `throw …NotFound` inside the service spreads that error-handling into every method
  that happens to need the object.
- **It's primitive obsession.** `string $nodeId` where a `WorkflowNode` is meant —
  the type doesn't say what the value is for.
- **It couples the method to the container's lookup API** (`$workflow->graph->nodeById`)
  for no reason — the method only ever wanted the node.

The caller resolves once, where the id was born, and every downstream method is
honest about its inputs.

### What is NOT this sin

- **A Registry / Repository keying into its OWN store** — `for($type)` returning
  `$this->providers[$type->value] ?? throw …`. Looking a value up by key against
  `$this` is the object's whole job; the container is not something the caller holds.
- **A boundary entry point** — a controller action or console command resolving a
  **route/argument id** (`__invoke(Request $r, Workflow $w, string $node)`). The id
  arrives as a string from the wire; the boundary *is* where it gets resolved, and
  there's no caller upstream to hand an object.
- **Reflection / generic collection access** — `$reflection->getProperty($name)`,
  `$collection->get($key)`. The "container" is a language facility or data structure,
  not a domain object the caller resolved from.
- **A genuine lookup API whose contract is "by id"** — a method that *exists* to
  resolve (`findById`, `descriptorFor`) and hands the result straight back. The smell
  is only when a method resolves an id **and then orchestrates** on the result while
  pretending it needed the container.

### The tell

A method signature pairs a domain object with a `string`/`int` id, and the body's
first move is `$thatObject->…->somethingById($thatId)`. Ask: *did the caller already
have what it needs to resolve this?* If yes, move the lookup to the caller and change
the parameter to the resolved type.
PRINCIPLE;
    }


    public function related(): array
    {
        return [
            ValueObjects::class => "the object you pass IS the resolved type — give data a type, don't thread an id + its container.",
            TellDontAsk::class => "unpacking a container param to work its target is feature envy on the container.",
        ];
    }
}
