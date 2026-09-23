# The Roslyn bridge's output

`roslyn-bridge <path>...` writes one JSON document to stdout. Version 1:

```json
{
  "version": 1,
  "files": [
    { "path": "/abs/path/File.cs", "errors": 0, "root": { "kind": "CompilationUnit", "start": 0, "end": 812, "children": [] } }
  ]
}
```

`errors` counts the file's syntax errors — a file the parser could not read whole.

## A node

Every syntax node, nested as Roslyn nests them. Tokens and trivia (comments, whitespace) are not nodes.

| key | when | what |
|---|---|---|
| `kind` | always | Roslyn's `SyntaxKind` name — `MethodDeclaration`, `IfStatement`, `InvocationExpression` |
| `start`, `end` | always | the node's span in the file, `[start, end)` in UTF-16 code units, trivia excluded |
| `children` | when it has any | its child nodes, in source order |
| `name` | declarations and names | a type's, member's, parameter's, local's or identifier's name |
| `text` | literals and interpolated text | the value as written, unescaped |
| `operator` | binary, assignment and unary expressions | the operator token, e.g. `==`, `??`, `+=`, `!` |
| `modifiers` | members, local functions, parameters | e.g. `["public", "static", "override"]` |
| `type` | expressions the compiler typed | the type, fully qualified (`global::System.String`) |
| `nullable` | with `type` | whether the type is annotated nullable (`string?`) |
| `target` | invocations and object creations it resolved | `{ "type", "name", "parameters" }` — the method called, its containing type and parameter types |
| `symbol` | member declarations | the declared member, fully qualified |
| `inherited` | members that override or implement another | `true` |

A fact the compiler could not resolve is absent — never guessed, never `null`.
