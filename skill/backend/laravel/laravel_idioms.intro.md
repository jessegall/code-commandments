The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
to hang behaviour on. Reach for those. Raw `->input()`, untyped `->get()`, `app()`-in-a-method, a
repeated `where()` chain, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.