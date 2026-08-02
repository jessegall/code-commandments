# Writing a Scribe — the auto-fix half

A **detector** finds the sin. A **scribe** rewrites it away. `judge` never fixes; `repent` runs the
scribes. This is the contract, the tools, and one worked example end to end.

> Write a scribe only when the fix is **mechanical and unambiguous** — one right answer the rewrite
> can always produce. If the honest fix depends on what the code MEANS, a scribe launders the
> problem. See "Should it auto-fix?" in the skill.

## The contract

Two pieces. The detector declares that its sin can be repented and names the scribe:

```php
final class InlineDocblockDetector implements Detector, Repentable
{
    public function sin(): Sin { return new InlineDocblock(); }

    public function scribe(): string { return InlineDocblockScribe::class; }

    public function find(Codebase $codebase): array { /* … */ }
}
```

`scribe()` may return a **class-string**, a **configured instance** (so two detectors can share one
base scribe, each handing back its own tuned variant), or a **callable factory** returning either.

The scribe extends `RepentScribe` and implements one method:

```php
abstract public function rewrite(array $findings): array;   // path => new file content
```

**A scribe does NOT re-scan.** The runner hands it *this detector's* `find()` results, so it starts
from the exact matches the detector already located — every finding is a full `NodeMatch`
(backend) or `ElementMatch` (frontend), carrying its node, its file, its span, `enclosingClass()`,
`fields()`, and the rest of the arsenal. Reach for those; never re-derive what the finding knows.

If the fix needs the WHOLE codebase (to resolve a target class's shape before deciding the rewrite
is safe), implement `NeedsCodebase` — the runner injects the scanned codebase before calling
`rewrite`. A purely local rewrite ignores it.

## How a scribe edits source

**Never by line surgery, and never with a regex** (`preg_*`, `strpos`, `ctype_*` are banned in the
scribe layer, and a test enforces it). Two layers do all the work:

| Layer | Owns |
|---|---|
| **`Draft`** | the edit set — `edit(Span, text)`, collapsed into `rewrites(): path => content` |
| **`Writer`** | every INTENT-level rewrite: `replace`, `replaceDocblock`, `replaceComments`, `insertAt`, `stampAttribute`, `ensureImport`, `dropImport`, `dropModifier`, `removeReturnType`, `deleteStatementLine`, `moveBefore`, `reorder`, `rewriteRange` |
| **`Span`** | ALL offset math — `indentAt`, `ownLineIndent`, `before`, `after`, `skipWhitespace`, `blockOpener`, `reindentText` |

The shape is always the same:

```php
$draft = $this->draft([]);

foreach ($findings as $finding) {
    Writer::for($draft, $finding)->replace($node, $text);
}

return $draft->rewrites();
```

**A missing write op is a method to ADD to the `Writer`, not a raw edit in your scribe.** That is
the cardinal rule for the rewrite half: one canonical writer, so no scribe re-invents indentation,
keyword-hunting, or attribute insertion. Likewise, anything that READS a shape (a docblock's form,
a type's head) belongs in the `Ast\Support\*` layer where the detectors can share it.

## What it reports

Nothing, directly. `rewrite()` returns `path => new content` for **changed files only** — it is
pure, and writes nothing to disk. `repent` diffs that against the working tree; `--dry-run` prints
the unified diff instead of applying it. So "what changed" is the diff, and a file you did not
change must not appear in the array.

## The chain

`repent` runs an ordered `ScribeChain` of `ScribeStep`s — one step per repentable detector, plus
the maintenance scribes. Each step **re-scans** through a `WorkingCopy` overlay, so it sees every
earlier step's edits; in-place fixers run first, extractors last. A consumer can reorder, replace
or remove a step by name (`prepend`/`append`/`before`, middleware-style). You do not wire any of
this: implementing `Repentable` enrols your detector in the chain.

## Worked example: fold a docblock down, and drop it when nothing is left

The fix: delete every tag line that merely restates the signature, and if that empties the block,
delete the block itself.

Start where the scribe layer ends. "Which lines are ceremony" is a question about the SHAPE of a
docblock, so it belongs on `Ast\Support\Docblock` — the single home of that concept, beside
`canonical`, `merge`, `foldable` and `retype`. Add it there first:

```php
// src/Ast/Support/Docblock.php — the reusable half of the fix
public static function withoutTags(string $text, string ...$tags): array   // remaining content lines
```

Then the scribe is thin, and every line of it is a call into a tool something else already shares:

```php
final class CeremonyDocblockScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $finding) {
            // Guard the finding: a scribe that assumes a shape the detector never
            // promised will corrupt a file.
            if ($finding instanceof NodeMatch && $finding->node?->getDocComment() !== null) {
                $this->fold($draft, $finding);
            }
        }

        return $draft->rewrites();
    }

    private function fold(Draft $draft, NodeMatch $finding): void
    {
        $doc = $finding->node->getDocComment();
        $indent = Span::ownLineIndent($finding->file->source, $doc->getStartFilePos()) ?? '';
        $kept = Docblock::withoutTags($doc->getText(), 'param', 'return');
        $writer = Writer::for($draft, $finding);

        // Nothing but ceremony was in it — the whole block goes, its line with it.
        $kept === []
            ? $writer->deleteStatementLine($doc)
            : $writer->replaceDocblock($finding->node, Docblock::merge([implode("\n", $kept)], $indent));
    }
}
```

Three things to copy from it:

1. **The shape question is answered in `Ast\Support`, never inline.** `Docblock` is the single
   home of "what a docblock IS", so the rule that FINDS a ceremony block and the fix that REMOVES
   it can never disagree about what counts. A predicate you need but don't find goes there — and
   the detector gets to use it too.
2. **Guard the finding** before touching it, then skip what you cannot safely fix.
3. **Both outcomes go through the `Writer`** — `deleteStatementLine` and `replaceDocblock`. There
   is no offset arithmetic anywhere in the scribe; `Span` did it.

## Prove it

A scribe is tested the way a detector is: build a codebase from a string, run the detector, feed
its findings to the scribe, assert on the returned content.

```php
private function fix(string $php): string
{
    $codebase = Codebase::fromString($php, '/proj/app/Page.php');
    $rewrites = new YourScribe()->rewrite((new YourDetector)->find($codebase));

    return reset($rewrites) ?: $php;
}
```

Assert on the result, and assert on what must NOT change — the sibling tag left alone, the prose,
the spacing. Then preview it on real code:

```bash
vendor/bin/commandments repent src --only=YourDetector --dry-run
```

Read the diff by eye. **A broken or incorrect `repent` result is itself a bug** — report it with
`commandments report`, referencing both the source and the broken output.

### The checks a scribe must survive

- It changes ONLY what the sin is about. Prose, spacing, and sibling declarations survive byte-for-byte.
- It carries the whole declaration: retyping a signature and leaving its docblock (or a now-dead
  import) behind means the file says two things at once, which is a worse state than the sin.
- Running it twice changes nothing the second time.
- A finding it cannot safely fix is SKIPPED, not half-fixed. The detector still reports it, and a
  human finishes the job.
