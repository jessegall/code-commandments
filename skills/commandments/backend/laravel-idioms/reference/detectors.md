# Laravel idioms — typed access, injected deps, behaviour on the model — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`config-read`** — `config('…')` read inside a class — `ConfigReadDetector`
- **`container-reach`** — `app()`/`resolve()` reach inside a container-resolved class — `ContainerReachDetector`
- **`dead-config-key`** — A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt — `DeadConfigKeyDetector`
- **`dead-event-wiring`** — An `Event::listen` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring — `DeadEventWiringDetector`
- **`duplicated-config-default`** — A config key whose default is stated TWICE — once in the config file, again as the reader's inline fallback — two sources of truth that drift silently — `DuplicatedConfigDefaultDetector`
- **`facade-call`** — Laravel facade call (`Cache::`, `Log::`, `Mail::` …) — `FacadeCallDetector`
- **`mass-update-at-call-site`** — Bare `$model->update([...])` mass-array update at a call site — `MassUpdateAtCallSiteDetector`
- **`model-mutation-at-call-site`** — Set-property-then-`save()` at a call site (should be an intention method) — `ModelMutationAtCallSiteDetector`
- **`orphaned-binding`** — A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor — `OrphanedBindingDetector`
- **`raw-request-input`** — Raw `->input()/->get()/->query()/->post()` on a Request — `RawRequestInputDetector`
- **`request-accessor-recast`** — Re-coercing a typed request accessor at a call site — `$request->string('id')->toString()` or `(string) $request->string('id')` instead of a named getter on a request class — `RequestAccessorRecastDetector`
