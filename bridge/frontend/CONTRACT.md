# The frontend bridge's output

The frontend bridge writes the generic tree contract in [`contract/CONTRACT.md`](../../contract/CONTRACT.md),
version 1, for TypeScript and Vue. This document says only what is particular to it: how it is run, what it
fills, and what it leaves to the engine.

```
node bridge/frontend/dist/bridge.mjs [--write=PATH]... [--rename=FROM=TO]... PATH...
node bridge/frontend/dist/bridge.mjs --serve [--rename=FROM=TO]...
```

`dist/bridge.mjs` is one bundled file, with TypeScript's standard library (`lib.*.d.ts`) beside it, so it needs
only `node`. It is built from `src/` by `npm run build` (or `go generate ./engine/frontend`), and committed.

| flag | what |
|---|---|
| `PATH` | a file, or a folder read for every `*.vue` and `*.ts` under it; links, dot folders, `node_modules`, `vendor`, `site-packages` and `__pycache__` are skipped. Files are written sorted, each once |
| `--write=PATH` | a file or folder that is judged. When any is given, every other file is written with `"context": true` |
| `--rename=FROM=TO` | writes paths under `FROM` as under `TO`, so a committed stream names no machine's own folders |
| `--serve` | answers each stdin line `{"paths": [...], "write": [...]}` with a whole stream, header to trailer |

## One stream, one program

A run writes ONE stream: `language` `vue` when it holds any `.vue` file, `typescript` otherwise. Each file names
its own language. The files share one TypeScript program, because they share one symbol space: a `.vue` file's
scripts are checked as `<file>.vue.ts`, the file's text with everything outside its `<script>` blocks blanked,
so every offset the checker gives is an offset in the `.vue` file, and `import X from './X.vue'` resolves as
TypeScript resolves any extension. Compiler options come from the nearest `tsconfig.json` (its referenced
projects' `paths` merged in when it has none), and the project's `vite.config.*` `resolve.alias` is read
statically and added to them.

## What it fills

- **TypeScript**: every node, `kind` the compiler's `SyntaxKind` and `field` its property name, with `role`,
  `is`, `name`, `literal`/`value`, `operator`, `modifiers`, `flags`, `declared` and `returns`. Tokens are not nodes.
- **Vue**: `Component` → `Block` → `Element`/`Text`/`Interpolation`, with `Attribute` and `Directive` children
  in field `attributes`, as the contract's *Vue* section says. A template expression is parsed by TypeScript,
  its spans file-absolute; an event handler that is statements rather than one expression is its statements,
  each in field `value`. A `v-for` alias and a `v-slot` value are patterns (`role: pattern`).
- **Checker facts** on script code: `resolved`, `target`, `refers`, `symbol`, and `resolves` on imports,
  exports and component tags (an `Element` whose name, as written or in PascalCase, is a name the script imports).
  `program.aliases` lists the vite aliases. Template expressions carry no checker facts: nothing types them.
- **Comments** from TypeScript's comment ranges and Vue's template comments (`kind: markup`), attached as the
  contract's *Attachment* says.
- **`errors`** counts TypeScript's parse diagnostics and Vue's parse errors; a file read only in part is still written.

## What it leaves to the engine

`constant`, `inherited` and comment `refs` resolution, as the contract's *Who fills it* table says.

## Regenerating the committed streams

```
node bridge/frontend/dist/bridge.mjs --rename=tests/Fixtures=/fixtures <the files> > contract/samples/vue.jsonl
```
