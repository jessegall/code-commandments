`Shop.Domain` says "this is the business, and it knows nothing about how it is shown"; `Shop.Web` says "this
is built on top of the domain". The claim is worth exactly as much as the code: one `OrderPage` used inside
`Shop.Domain` and the two can no longer be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The project compiles and the tests pass. When both
namespaces live in one project, the compiler never stops a reference going the wrong way.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.json`, each layer naming the layers
it may use — here the domain uses only itself, the application its domain, and the web layer both:

```json
"configure": {
    "csharp/NamespaceDependencyDetector": [
        {"layer": ["Shop.Domain"]},
        {"layer": ["Shop.Application", ["Shop.Domain"]]},
        {"layer": ["Shop.Web", ["Shop.Application", "Shop.Domain"]]}
    ]
}
```

### What is judged

- **Every reference the compiler resolved**, not only the `using` lines: a type in a declaration, the type
  of a value, the target of a call, and the type arguments inside any of them. A fully qualified name counts
  the same as one brought in by a `using`.
- **Only the project's own types.** A framework or package type is always allowed.
- **Only from a declared layer.** A layer contains its own nested namespaces, so references within a layer
  are fine.
- **Cycles, declared or not.** Two of the project's namespaces that each use the other are one namespace,
  whatever the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
reference is the accident; when you conclude the declaration is wrong, say so to the user with your
reasoning. Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind an interface the lower layer owns and the upper layer implements.