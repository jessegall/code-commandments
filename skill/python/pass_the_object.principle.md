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