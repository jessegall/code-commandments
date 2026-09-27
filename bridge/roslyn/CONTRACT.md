# The Roslyn bridge's output

The bridge writes the generic tree contract in [`contract/CONTRACT.md`](../../contract/CONTRACT.md), the one every
language bridge writes and the engine reads: one tree, one stream framing, one set of type, symbol and comment fields.
What is particular to C# — its node kinds, its facts (`forgivesNull`, `code`), how it names a symbol, and the outside
declarations it reads from the referenced assemblies — is written there, in each section's C# row.

| Invocation | What it writes |
|---|---|
| `roslyn-bridge <path>...` | every C# file under the roots, or the files named, compiled together without building the project, one project at a time, its compilation let go once written |
| `roslyn-bridge --serve` | the same, kept warm: a request per line on stdin, `{"paths": [...]}`, answered with its lines; `"write": [...]` limits the files written in full, the rest written with `context: true` |
| `roslyn-bridge --listen <port>` | `--serve` over a TCP socket, a connection at a time: the service a session keeps up |
| `--diagnose` | the compiler's most common errors on stderr first: why a call did not resolve |
