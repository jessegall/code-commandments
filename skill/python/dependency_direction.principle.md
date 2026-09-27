`shop.ui.elements` says "these are the primitives"; `shop.ui.shared` says "these are built FROM the
primitives". The claim is worth exactly as much as its imports: one `from shop.ui.shared import Panel` inside
`elements` and the two cannot be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The import runs and the tests pass. Python even hands you
the escape hatch — move the import into the function body and the circular-import error goes away — and
the cycle is still there, only hidden.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.json`, each layer naming the layers
it may use — here the primitives use only themselves, the shared layer is built from them, and the domain
knows nothing about the UI:

```json
"configure": {
    "python/NamespaceDependencyDetector": [
        {"layer": ["shop.ui.elements"]},
        {"layer": ["shop.ui.shared", ["shop.ui.elements"]]},
        {"layer": ["shop.domain"]}
    ]
}
```

### What is judged

- **Every import**: `import x`, `from x import y`, a relative `from ..x import y`, and one written inside a
  function or behind `if TYPE_CHECKING:`. Where it sits does not change what it depends on.
- **Only declared packages.** The standard library, a third-party package, or a package the project never
  declared is always allowed.
- **Only from a declared layer.** A layer contains its own sub-packages, so imports within a layer are fine.
- **Cycles, declared or not.** Two of the project's packages importing each other are one package, whatever
  the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
import is the accident; when you conclude the declaration is wrong, say so to the user with your reasoning.
Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind a protocol the lower layer owns. An import moved into a function body is not a fix: the arrow is
still there.