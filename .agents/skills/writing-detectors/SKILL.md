---
name: writing-detectors
description: How to author a Sin Detector end-to-end in the Go engine — a type with Sin() and Find(), AST/semantic over names, the fluent one-check-per-Where style, registered in init(), proven by its engine's fixture, calibrated on real code. Read this BEFORE adding or changing a detector.
---

# Writing a Sin Detector

A detector is **thin**: it finds the sin and names it; the sin points at the skill that teaches the fix. No fix
logic, no severity, no rubric — the skill teaches, the detector finds.

```go
// DeepNestingDetector finds an if nested two ifs deep within its function.
type DeepNestingDetector struct{}

func init() { detectors.Register(catalog.Backend, DeepNestingDetector{}) }

// Sin is the sin the detector finds.
func (DeepNestingDetector) Sin() sins.Sin { return backendsins.DeepNesting{} }

// Find is every if inside two or more ifs of its own function.
func (DeepNestingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		Where(engine.As(php.Node.IsDeeplyNestedIf)).
		Get()
}
```

It lives in `detectors/<engine>/`, its sin in `sins/<engine>/`, and it enrols itself: `init()` registers it,
and `registry` imports every detector package. There is no list to add it to.

## The rules

1. **AST/semantic over names — the cardinal rule.** Classify by what the tree and the resolved types say
   (extends/implements, attributes, constructor shape, resolved type), never by a class, method or variable
   name, a suffix, or a hardcoded list. A name check is a smell to justify.
2. **Compose the engine, don't poke the tree.** Use the `engine.Codebase` selectors, `engine.Query` and the
   language's decorator predicates. Missing a predicate? Add it to the right layer ([[detector-engine]]), not
   inline in the detector.
3. **One check per `Where`/`Reject` line.** Read it like a sentence.
4. **Best-of-the-best only.** A detector must catch a real, principled architectural sin with few false
   positives. Skip crude heuristics (raw counts), role-inference-by-name, and anything needing
   natural-language understanding. Curate.

## The cadence

1. **Unit test first** (red → green) where the engine has a source builder: `frontendtest.FromSource`,
   `pythontest.FromSource`, `csharptest.FromSource` build a codebase through the real bridge. Cover the flag
   case AND the look-alikes it must NOT flag. A backend detector is proven by the shop fixture directly.
2. **Implement** the detector, its sin, and any engine helper it needs. A detector still being calibrated
   carries `Unpublished()` — every catalog skips it — until its hits read clean.
3. **Prove it in the fixture** ([[detector-fixtures]]): mark ≥3 DIVERSE examples with the SIN's name, keep a
   righteous twin it must not flag, and write a fixed twin — the sinful code REPAIRED the way the rule says,
   which the published skill shows as "Good". A backend fixture change needs its stream regenerated:
   `scripts/dev go generate ./engine/php`, then `scripts/dev go test ./engine/php/shop`.
4. **Validate on real code.** Build the tool (`scripts/build`) and read the hits:
   `bin/commandments judge ../some-app/src --sin=your-sin --no-checklist`. A real false positive → tighten
   the detector (a principled `Reject`, never a name list) before shipping.

## Pointing at the skill

The sin your detector returns names the skill that teaches the fix plus the one-line description the docs
project from — `judge` prints that skill, so the agent reads one skill and resolves the whole group. Keep the
sin's skill and description accurate; the generated "when it fires" rows regenerate from them
(`composer sins`, or the pre-commit hook).

## Related

- [[detector-engine]] · [[detector-fixtures]] · [[writing-exemptions]]
- Commit conventions in [[releasing]].
