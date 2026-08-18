# Pass the object, not its id — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`computed-boolean-argument`** — a bool-only chooser whose callers all compute the flag off the same object (take the object and ask it) — `ComputedBooleanArgumentDetector`
- **`converted-argument`** — A parameter declared in the wrong currency — call site after call site wraps the same argument in the same conversion (`Raises::of(ClassAlias::of($interaction), …)`) because the callee asks for the converted form instead of the value — `ConvertedArgumentDetector`
- **`derived-argument`** — Handing one subject to a call TWICE over — whole and again flattened (`persist($request, $request->shopId())`), or flattened several ways (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive every piece from the subject itself — `DerivedArgumentDetector`
- **`param-resolved-from-param`** — Unpacking the target out of a container param — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)`, then works on the target while the container is only packaging — `ParamResolvedFromParamDetector`
