# Page Objects — the Data class builds the whole page — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constructor-orchestration`** — A page object fills a public slot imperatively in the constructor (`$this->x = $this->projector->…()`) where a `#[Computed]` property hook would describe it in place — `constructor-orchestration`
- **`injected-service-not-hidden`** — A page object injects a service (`#[FromContainer]`, …) into a public property without `#[Hidden]` — it leaks into the generated TypeScript type — `injected-service-not-hidden`
- **`manual-output-transform`** — A `Data` computed slot hand-flattens a value object into a wire array, instead of a `#[WithTransformer]` that owns the serialized shape — `manual-output-transform`
- **`page-object-missing-typescript`** — A page object travels back in a response but carries no `#[TypeScript]` — the `.vue` page reads it as untyped `any`, so the whole page-prop contract goes unchecked — `page-object-missing-typescript`
- **`service-location-in-page-object`** — A page object reaches into the container with `app()`/`resolve()` instead of injecting the collaborator via `#[FromContainer]` — `service-location-in-page-object`
- **`transformer-without-ts-type`** — On a Data class exported with `#[TypeScript]`, a custom `#[WithTransformer]` changes the wire shape and the generated type still describes the PHP value. — `transformer-without-ts-type`
