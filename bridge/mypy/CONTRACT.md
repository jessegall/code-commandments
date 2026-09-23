# The mypy bridge's output

`python bridge.py <path>...` writes JSON lines to stdout: one object per line, so a reader holds one file at a
time. Version 1:

```json
{"version": 2}
{"path": "/abs/path/shop/cart.py", "types": [{"start": 120, "end": 131, "type": "shop.cart.Cart", "nullable": false, "class": "shop.cart.Cart"}]}
{"resolution": {"expressions": 9992, "typed": 7823}}
```

The first line names the version, then comes one line per module, and the `resolution` line closes the answer.
Paths are absolute with symbolic links resolved. `resolution` says how many expressions mypy typed and how
many of them the bridge wrote, which is every one except those that are `Any`.

`--serve` answers each request on stdin with the same lines. It holds the checked project in memory, as mypy's
daemon does, so a later request for the same paths re-checks only the modules whose files changed. A request
is one line:

```json
{"paths": ["/abs/src"], "write": ["/abs/src/shop/cart.py"], "python": "/abs/.venv/bin/python"}
```

| key | what |
|---|---|
| `paths` | the files and folders whose modules are typed; mypy names each module from its package layout |
| `write` | when given, only these files get a line; the rest still inform the types |
| `python` | the interpreter whose installed packages resolve third-party imports; without it they are untyped |

## A type

One entry per expression mypy resolved, in source order:

| key | when | what |
|---|---|---|
| `start`, `end` | always | the expression's span in the file, `[start, end)` in UTF-8 bytes, the offsets the PHP parser gives the same expression |
| `type` | always | the type as mypy writes it: `str \| None`, `list[int]`, `shop.cart.Cart` |
| `class` | the type is a class instance, or one class or `None` | the class's fully qualified name: `builtins.str`, `shop.cart.Cart` |
| `nullable` | always | whether the type is a union with `None` |
| `constructs` | the expression names a class — `str`, `Money` in `Money.of` — so calling it builds one | the class's fully qualified name: `builtins.str`, `shop.money.Money` |

A type mypy could not resolve (`Any`) is absent: never guessed, never `null`. mypy also types a few shapes of
its own, such as the pieces of an f-string, that the PHP parser has no node for; a reader matching by span
never meets them.
