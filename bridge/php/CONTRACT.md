# The PHP bridge's output

The PHP bridge writes the generic tree contract in [`contract/CONTRACT.md`](../../contract/CONTRACT.md),
version 1, with `language: "php"`. This document says only what is particular to it: how it is run, what
it fills, and what it leaves to the engine.

```
php bridge/php/bridge.php [--write=PATH]... [--autoload=FILE] [--rename=FROM=TO]... PATH...
php bridge/php/bridge.php --serve [--autoload=FILE] [--rename=FROM=TO]...
```

It parses with nikic/php-parser (the newest PHP version it supports) and runs its `NameResolver`. It
needs PHP and this package's own `vendor/`, which is where php-parser comes from.

| flag | what |
|---|---|
| `PATH` | a file, or a folder read for every `*.php` under it; `vendor/` and dot folders are skipped. Files are written sorted, each once |
| `--write=PATH` | a file or folder that is judged. When any is given, every other file is written with `"context": true` |
| `--autoload=FILE` | the scanned project's `vendor/autoload.php`, for reflecting outside symbols. Without it, the nearest `vendor/autoload.php` above the first path. With none found, no outside symbols are written |
| `--rename=FROM=TO` | writes paths under `FROM` as under `TO`, so a committed test stream names no machine's own folders |
| `--serve` | answers each stdin line `{"paths": [...], "write": [...]}` with a whole stream, header to trailer |

## What it fills

- **Syntax**: every php-parser node, `kind` its `getType()` and `field` its sub-node name, with `role`,
  `is`, `name`, `literal`/`value`, `operator`, `modifiers`, `flags`, `declared` and `returns`.
- **An interpolated string's literal parts** (`InterpolatedStringPart`) are `literal: "string"` with their
  decoded `value`, so the text between the interpolations reads without its escapes.
- **The file root** is a `File` node spanning the whole file, its top-level statements in field `stmts`
  in order. php-parser has no file node, and a file holds more than one statement whenever it opens with
  `declare(strict_types=1)`.
- **Names**: `declared`/`returns` name fully qualified classes. `refers` is on every fully qualified class
  name, on a fully qualified function call's name (`Shop\total()`), and on each class or function a `use`
  imports, group uses included. An unqualified function or constant name that PHP resolves only at run
  time refers to nothing.
- **Imports** carry `modifiers: ["function"]` or `["const"]` on a `use function` or `use const`, on the
  statement and on each item that writes its own.
- **`symbol`** on every class-like, function, method, property, promoted parameter, class constant and
  enum case, spelled as the contract's *Symbols* table spells it. An anonymous class's members have none.
- **Comments** from php-parser's tokens, attached as the contract's *Attachment* says.
- **`program.symbols`**: every class the scan names but does not declare, reflected through the project's
  autoloader: its ancestors, traits, modifiers, and its own methods, properties and constants with their
  native types. The set is closed. Docblock types are not read, so no member carries `documented`.
- **`errors`** counts what php-parser's error recovery skipped; a file it read only in part is still written.

## What it leaves to the engine

`resolved`, `target`, `constant`, `inherited`, docblock types and comment `refs` resolution, as the
contract's *Who fills it* table says. The trailer carries `files` and no resolution counts: the bridge
resolves no expressions.

## Regenerating the committed streams

```
php bridge/php/bridge.php --rename=tests/Fixtures=/fixtures <the files> > contract/samples/php.jsonl
cd fixture && go generate ./
```
