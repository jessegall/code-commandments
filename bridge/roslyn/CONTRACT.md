# The Roslyn bridge's output

`roslyn-bridge <path>...` writes JSON lines to stdout — one object per line, so a reader holds one file
at a time however large the project. Version 2:

```json
{"version": 2}
{"path": "/abs/path/File.cs", "errors": 0, "root": {"kind": "CompilationUnit", "start": 0, "end": 812, "children": []}}
{"resolution": {"calls": 1, "resolved": 1}}
```

The first line names the version; then one line per file; the `resolution` line closes the answer.
`errors` counts the file's syntax errors — a file the parser could not read whole. `test: true` marks a
file of a test project (`IsTestProject`, as the test SDK sets it). `resolution` says how
many calls and object creations the run saw and how many the compiler could resolve with the references
it found (frameworks from the installed reference packs, packages from `obj/project.assets.json` or the
NuGet cache — never a build). `--serve` answers each request on stdin with the same lines.

## A node

Every syntax node, nested as Roslyn nests them. Tokens and trivia (comments, whitespace) are not nodes.

| key | when | what |
|---|---|---|
| `kind` | always | Roslyn's `SyntaxKind` name — `MethodDeclaration`, `IfStatement`, `InvocationExpression` |
| `role` | always | `statement`, `expression`, `member` (a type or member declaration), `type` (a name in a type position) or `other` (a parameter, an argument, a clause) — from Roslyn's own class hierarchy |
| `start`, `end` | always | the node's span in the file, `[start, end)` in UTF-8 bytes — the offsets a byte-oriented reader such as PHP uses — trivia excluded |
| `children` | when it has any | its child nodes, in source order |
| `name` | declarations and names | a type's, member's, parameter's, local's or identifier's name |
| `text` | literals and interpolated text | the value as written, unescaped |
| `operator` | binary, assignment and unary expressions | the operator token, e.g. `==`, `??`, `+=`, `!` |
| `modifiers` | members, local functions, parameters | e.g. `["public", "static", "override"]` |
| `type` | expressions the compiler typed | the type, fully qualified with `?` for a nullable reference (`global::System.String?`), never a keyword like `string` |
| `nullable` | with `type` | whether the type is annotated nullable (`string?`) |
| `type` on a `CatchDeclaration` | when it resolves | the exception type the `catch` catches, fully qualified |
| `forgivesNull` | a null-forgiving `!` whose operand is declared nullable (a `T?` field, property, local, parameter or return) | `true` |
| `constant` | expressions with a compile-time value | `true` — a literal, an enum member, a `const`, or arithmetic on them, as the compiler folds it |
| `target` | invocations and object creations it resolved | `{ "type", "name", "parameters" }` — the method called, its containing type and parameter types |
| `symbol` | member declarations | the declared member, fully qualified |
| `inherited` | members that override or implement another | `true` |

A fact the compiler could not resolve is absent — never guessed, never `null`.
