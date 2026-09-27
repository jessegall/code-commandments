# The mypy bridge's output

The bridge writes the generic tree contract in [`contract/CONTRACT.md`](../../contract/CONTRACT.md), the one every
language bridge writes and the engine reads: each module's `ast` as nodes, with mypy's type joined to each expression
by exact span as `resolved`, plus the `module`, `resolver`, `program.packages` and the Python `extras`. `tree.py`
writes it; `session.py` holds the mypy session that types the project, checked in memory between requests so a later
one re-checks only the modules whose files changed.

`python tree.py <path>...` writes one stream. `--serve` answers each request line on stdin with a full stream,
header to trailer, writing a module outside `write` with `context: true`. A request is one line:

```json
{"paths": ["/abs/src"], "write": ["/abs/src/shop/cart.py"], "python": "/abs/.venv/bin/python"}
```

| key | what |
|---|---|
| `paths` | the files and folders whose modules are typed; mypy names each module from its package layout |
| `write` | when given, only these files are written in full; the rest still inform the types |
| `python` | the interpreter whose installed packages resolve third-party imports; without it they are untyped |

A type mypy could not resolve (`Any`) is absent: never guessed, never `null`.
