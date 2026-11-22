# Laravel idioms — typed access, injected deps, behaviour on the model — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`bare-scope-escape`** — an Eloquent query drops every global scope, including ones added later — `bare-scope-escape`
- **`config-read`** — `config('…')` read inside a class — `config-read`
- **`container-reach`** — `app()`, `resolve()` or a container's `make()` reach inside a container-resolved class — `container-reach`
- **`dead-config-key`** — A config key nothing reads — dead surface left behind by a deleted feature, which new code may wrongly adopt — `dead-config-key`
- **`dead-event-wiring`** — An `Event::listen` on an event class no live code path can fire — a listener chain that dead-ends but reads as live wiring — `dead-event-wiring`
- **`duplicated-config-default`** — A config key whose default is stated TWICE — once in the config file, again as the reader's inline fallback — two sources of truth that drift silently — `duplicated-config-default`
- **`facade-call`** — Laravel facade call (`Cache::`, `Log::`, `Mail::` …) — `facade-call`
- **`handed-out-collection`** — A getter whose whole body returns the object's own mutable collection, untouched: every caller holds the same one and may add, remove or reorder it behind the object's back. — `handed-out-collection`
- **`hard-coded-route-path`** — A path written out by hand where a named route already declares it — `hard-coded-route-path`
- **`mass-update-at-call-site`** — Bare `$model->update([...])` mass-array update at a call site — `mass-update-at-call-site`
- **`model-mutation-at-call-site`** — Set-property-then-`save()` at a call site (should be an intention method) — `model-mutation-at-call-site`
- **`orphaned-binding`** — A container binding whose abstract nothing ever resolves — dead wiring that reads as load-bearing and survives every refactor — `orphaned-binding`
- **`query-in-loop`** — a query run inside a loop — one query per turn, so the work grows with the data while the code reads as though it were one call — `query-in-loop`
- **`raw-request-input`** — Raw `->input()/->get()/->query()/->post()` on a Request — `raw-request-input`
- **`request-accessor-recast`** — Casting the Stringable returned by a typed request accessor instead of using its `toString()` method. — `request-accessor-recast`
