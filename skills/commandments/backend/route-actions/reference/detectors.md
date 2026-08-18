# Route Actions — one operation, one entry point — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`boundary-duplicated-operation`** — The same domain operation hand-rolled at two DIFFERENT entry boundaries (a console command and an MCP tool, a controller and a command) — one operation with two implementations that drift — `BoundaryDuplicatedOperationDetector`
- **`dangling-route-name`** — A `route('x')` lookup naming a route no registration mints — a stringly cross-reference that only fails at runtime, as a 500 — `DanglingRouteNameDetector`
- **`duplicate-route`** — Two route registrations of the same verb bind different URLs to the SAME `[Controller, method]` — two names for one handler (invokable single-action controllers, commonly aliased to several canonical URLs, are exempt) — `DuplicateRouteDetector`
- **`duplicate-route-action`** — Two route actions in different controllers thinly delegate to the SAME operation (`return $this->exporter->export(...)`) — the same entry point twice — `DuplicateRouteActionDetector`
- **`route-delegates-to-controller`** — A route action forwards to ANOTHER controller's action (`return $this->otherController->action(...)`) — a redundant entry point onto an operation that already has one — `RouteDelegatesToControllerDetector`
