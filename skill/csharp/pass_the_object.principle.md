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