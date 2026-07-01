---
name: writing-detectors
description: How to author a v4 Sin Detector end-to-end — implement Detector (sin/find), AST/semantic over names, the fluent one-check-per-where style, TDD (red→green via Codebase::fromString), Catalog auto-discovery, the fixture proof, and validate-on-real-code. Read this BEFORE adding or changing a detector. Don't port v3 prophets 1:1.
---

# Writing a Sin Detector

A detector is **thin**: it finds the sin and points at the skill that teaches the
fix. No fix logic, no severity, no rubric — the skill teaches, the detector finds.

```php
final class FacadeCallDetector implements Detector
{
    public function sin(): Sin { return new FacadeCall(); }   // the sin — carries its skill + description

    public function find(Codebase $codebase): array                  // list<NodeMatch>
    {
        return $codebase
            ->whereStaticCall()
            ->where(fn (AstNode $n): bool => str_starts_with($n->staticCallClass() ?? '', self::FACADE_NS))
            ->get();
    }
}
```

It auto-enrolls — `Detectors\Catalog::all()` globs `Backend/*Detector.php`. No list
to register.

## The rules

1. **AST/semantic over names — the cardinal rule.** Classify by what the AST/type
   IS (extends/implements, attributes, constructor shape, resolved type), never by
   a class/method/variable name, suffix, or a hardcoded base list. A name check is
   a smell to justify. (See `prefer_ast_over_name_checks`.)
2. **Compose the engine, don't poke the AST.** Use `Codebase` selectors + `Query`
   filters. Missing a predicate? Add it to the right layer ([[detector-engine]]),
   not inline in the detector.
3. **One check per `where`/`reject` line.** Read it like a sentence.
4. **Best-of-the-best only.** A detector must catch a real, principled
   architectural sin with low false-positives. Skip crude heuristics (raw counts),
   role-inference-by-name, and anything needing NL/semantic understanding — they
   hurt the agent. Do **not** port the ~105 v3 prophets (`deprecated/`) one by one;
   the v4 system is better. Curate. (See `v4_dont_port_prophets`.)

## The cadence

1. **Unit test first** (red → green). `Codebase::fromString($php)`, run the
   detector, assert the matched `scope()`s. Cover the flag case AND the
   look-alikes it must NOT flag.
2. **Implement** the detector + any engine helper it needs.
3. **Prove it in the fixture** ([[detector-fixtures]]): mark `#[Sinful(YourSin::class)]`
   (name the SIN, not the detector) on ≥3 DIVERSE examples and keep a righteous twin it
   must not flag.
4. **Validate on real code.** Run `bin/commandments judge ../workflows/src
   --sin=your-sin` and read the hits. Real false positives → tighten the
   detector (a principled `reject`, not a name list) before shipping.

## Pointing at the skill

The `Sin` your detector returns (`sin(): Sin { return new YourSin(); }`) names the `Skill` class
that teaches the fix (by FQCN) plus the one-line `description` the docs project from — `judge` prints
that skill so the agent reads one skill and resolves the whole group. Keep the sin's `skill:` and
`description:` accurate; the generated "when it fires" rows regenerate from them (`composer sins`).

## Related

- [[detector-engine]] · [[detector-fixtures]] · [[writing-exemptions]]
- Commit conventions in [[releasing-and-propagating]].
