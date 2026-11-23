> Hand a function the thing it is about, whole. Do not make every caller take the thing apart first.
> Read this before you add a parameter that the caller derives from an object it already holds: a field of it, an id
> to look it up by, a yes-or-no worked out from it, and when a pass-the-object finding points here.
> The examples are pseudocode: read them as the language you are writing.

## The principle

**A signature asks for the subject, and the callee works out the rest.**

When a function takes pieces of an object instead of the object, three costs appear. Every caller repeats the same
derivation. The call site stops saying what it means (`inset(true, false, 4)`) and starts spelling out pieces. And
the day the callee needs one more piece, every caller changes.

The opposite mistake is the same sin seen from the other side: a function that takes a container and a key, and
whose first line looks the real subject up. It asked for more than it needs, and now it can fail to find it.

So: ask for exactly the object the body is about. Not its parts, and not the thing it lives in.

```pseudo
// Bad: three callers, three copies of the same unpacking
send(order.customer.email, order.customer.name, order.total, order.currency)

// Good
send(order)
```

## The sins

### derived-argument

The same object passed twice, once whole and once as a piece of itself, or passed only as several pieces. The
callee could derive each piece from the one object. Give the parameter the subject's type and move the derivations
inside.

```pseudo
// Bad
render(invoice, invoice.customer.name, invoice.lines.count)

function render(invoice: Invoice, customerName: string, lineCount: int) { … }

// Good
render(invoice)

function render(invoice: Invoice) {
    customerName = invoice.customer.name
    lineCount = invoice.lines.count
    …
}
```

### computed-boolean-argument

A yes-or-no parameter that every caller computes from the same object. The callee wanted the object and asked for a
flag; each caller now carries the rule for how the flag is worked out. Take the subject and ask it.

```pseudo
// Bad
inset = cornerInset(editor.isFullscreen() && !editor.hasSidebar())

function cornerInset(compact: bool): Inset { … }

// Good
inset = cornerInset(editor)

function cornerInset(editor: Editor): Inset {
    compact = editor.isFullscreen() && !editor.hasSidebar()
    …
}
```

### param-resolved-from-param

A function takes a container and a key and resolves the target itself. It needed the node and asked for the
workflow and an id. Take the resolved object; the caller resolves it once.

```pseudo
// Bad
function rename(workflow: Workflow, nodeId: string, name: string) {
    node = workflow.graph.node(nodeId)
    node.name = name
}

rename(workflow, id, "Start")

// Good
function rename(node: Node, name: string) {
    node.name = name
}

rename(workflow.graph.node(id), "Start")
```

## In your language

- **PHP.** A named constructor that takes the subject says it best: `CornerInset::for($editor)`.
- **Python.** An `_id` parameter beside the object it keys into is the tell: `def rename(workflow, node_id)`.
- **C#, TypeScript.** A `bool` parameter whose argument is always an expression over one object: pass that object.
- **Go.** Pass the struct (or a small interface it satisfies), not three of its fields.
