---
name: pass-the-object
description: Demand the resolved type you need, not an id plus its container. When a method takes a container object AND a key into it, then resolves the key against the container (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`), the lookup is misplaced — the caller passed both, so the caller already holds everything the lookup needs. Resolve at the caller and hand over the resolved OBJECT, and let the caller own the "not found" failure. Read this when a method signature pairs a domain object with a string/int id it then looks up inside.
---

# Pass the object, not its id

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A method that takes `(Workflow $workflow, string $nodeId)` and starts with
> `$workflow->graph->nodeById($nodeId)` is asking for the wrong things. It needs the
> **node**. The caller named the id and holds the workflow — so the caller should
> resolve, and pass the resolved object.

## The principle

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

## The smell → the fix

```php
// Bad — takes the container + a key, resolves the key inside, then orchestrates.
public function request(Workflow $workflow, string $nodeId): RemoteAgentSchemaRequest
{
    $node = $workflow->graph->nodeById($nodeId)->unwrapOrElse(
        static fn () => throw UnknownNodeDescriptorException::forKey($nodeId),
    );

    $config    = RemoteAgentNodeConfig::fromNode($node);
    $triggerId = $config->agentTriggerId()->unwrapOrElse(
        static fn () => throw RemoteAgentNodeException::noTriggerConfigured($nodeId),
    );

    $request = RemoteAgentSchemaRequest::open($workflow->id, $nodeId);
    $this->providers->for($config->providerType())->dispatch(/* … */);

    return $request;
}
```

```php
// Good — the caller resolved the node, its config, the provider and trigger, and
// hands over the finished facts. The service demands exactly what it uses; no graph
// lookup, no "unknown node" failure to own, no `$workflow` it only wanted for an id.
public function request(
    string $workflowId,
    string $nodeId,
    RemoteAgentProviderType $provider,
    string $triggerId,
): RemoteAgentSchemaRequest {
    $request = RemoteAgentSchemaRequest::open($workflowId, $nodeId);

    $this->providers->for($provider)->dispatch(new WorkspaceAgentDispatch(
        triggerId: $triggerId,
        conversationKey: $this->key(),
        input: SchemaRequestPromptComposer::compose($request->id),
        idempotencyKey: $this->key(),
    ));

    return $request;
}
```

The caller now resolves once, where the id was born, and every downstream method is
honest about its inputs.

## What is NOT this sin

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

## The tell

A method signature pairs a domain object with a `string`/`int` id, and the body's
first move is `$thatObject->…->somethingById($thatId)`. Ask: *did the caller already
have what it needs to resolve this?* If yes, move the lookup to the caller and change
the parameter to the resolved type.
