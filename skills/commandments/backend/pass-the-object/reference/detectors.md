# Pass the object, not its id — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`computed-boolean-argument`** — A parameter that's just true/false, computed by every caller from the same object it could be given instead. — `ComputedBooleanArgumentDetector`
- **`converted-argument`** — A parameter typed as the already-converted form instead of the raw value, so every call site repeats the same conversion before calling it (e.g. `Raises::of(ClassAlias::of($interaction), …)`). — `ConvertedArgumentDetector`
- **`derived-argument`** — Passing the same object twice — once whole and once broken into a piece (`persist($request, $request->shopId())`), or broken into several pieces at once (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive each piece itself from the one object. — `DerivedArgumentDetector`
- **`param-resolved-from-param`** — Unpacking the target out of a container parameter — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)` itself, when it could just receive the node directly. — `ParamResolvedFromParamDetector`
