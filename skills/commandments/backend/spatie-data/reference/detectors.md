# Spatie Data — let the class build itself — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`all-nullable-data`** — All-nullable "god" DTO — every field `?T`/defaulted (type doesn't tell the truth) — `AllNullableDataDetector`
- **`all-optional-data`** — Every field of a `Data` object is `T|Optional` — the type promises nothing is ever present; the absence belongs on the CONTAINER field where it's used — `AllOptionalDataDetector`
- **`data-collection-type`** — A `Data` property is TYPED as `DataCollection` — it should be `array` (or `Collection`) with `#[DataCollectionOf(X)]`; the `DataCollection` type emits malformed TypeScript and skips element-typed hydration — `DataCollectionTypeDetector`
- **`data-method-hint-collision`** — `@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`) — `DataMethodHintCollisionDetector`
- **`hook-missing-computed`** — A get-only property HOOK on a `Data` class lacks `#[Computed]` — Spatie reads the virtual property as a hydration INPUT, expects it in `::from()`, and crashes or silently drops it — `HookMissingComputedDetector`
- **`manual-hydration-loop`** — Collections hydrated with `::from()` per item instead of `#[DataCollectionOf]` + `::collect()` — `ManualHydrationLoopDetector`
- **`manual-input-cast`** — A `Data` value-object property is hand-built at every construction site, instead of a `#[WithCast]` / `Castable` that owns the hydration once — `ManualInputCastDetector`
- **`nested-type-missing-typescript`** — A `#[TypeScript]` Data has a property typed as a nested `Data` class that itself lacks `#[TypeScript]` — the transformer emits it as `undefined`, a silent hole in the generated type (a nested enum is fine; the enum collector auto-generates it) — `NestedTypeMissingTypeScriptDetector`
- **`new-data-object`** — `new <Data subclass>` instead of `::from()` / a `fromX()` factory — `NewDataObjectDetector`
- **`non-final-data`** — Data class not `final` / props not `readonly` promoted — `NonFinalDataDetector`
- **`null-to-optional-map`** — A producer hand-maps null→`new Optional` — `$x === null ? new Optional : Foo::from($x)` or `expr() ?? new Optional` — instead of one named factory (Spatie's `optional()` maps null→null, the opposite of what a `T|Optional` slot needs) — `NullToOptionalMapDetector`
- **`nullable-wire-object`** — A nested object on a `#[TypeScript]` Data is typed `T | null` — it ships `null` on the wire where `T | Optional` would OMIT it (what the frontend's `x?.` reads for "absent") — `NullableWireObjectDetector`
- **`prefer-optional-create`** — A raw `new Optional` is constructed in a runtime expression where Spatie's built-in `Optional::create()` factory reads clearer — `PreferOptionalCreateDetector`
