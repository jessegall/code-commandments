A **route action** — a controller method wired to a URL — is the thin seam between an HTTP request and
the domain. It validates/binds the request and **delegates into the domain**, then shapes the response.
That is all. The discipline is one line: **one operation, one entry point.**

### Delegate INTO the domain, never wrap another controller

An action delegates *down* — to a service, an action class, a repository that does the work. It must never
delegate *sideways*, into **another controller**. A controller that injects another controller and forwards
to its actions —

```
public function moveKey(Shop $shop, QuickPad $quickPad, MoveKeyRequest $request)
{
    return $this->quickPadController->moveKey($request, $quickPad);   // wrapping a controller
}
```

— is a **redundant entry point**: the operation already has a home (the wrapped controller / its route),
and this is a second door onto it that must be kept in sync forever. Extract the shared work into a
service (or an invokable action class) both routes call, or point the second route at the real action and
delete the wrapper.

### Don't duplicate a sibling action

Two actions with the same body — even a *thin* one — are the copy-paste smell the general duplication
rules deliberately skip at small sizes, because in a controller a duplicated action is a duplicated *entry
point*, not an incidental likeness. If `WorkflowExportController::export` and
`WorkflowEditorExportController::export` both just hand the request to a `WorkflowExporter`, they are the
same operation twice. Collapse them: one action, one route (or two routes → one action), the work in the
exporter.

### One route per action

Two route registrations pointing at the same `[Controller, method]` are two names for one thing — a
maintenance trap (middleware, names, and constraints drift apart). Register the action once; if you truly
need a second URL, make it a redirect, not a second binding onto the same handler.

**The exception — an invokable controller mapped to several URLs is fine.** A single-action controller
(`__invoke`) IS one operation, and answering it at several canonical URLs — the OAuth/OIDC well-known
discovery endpoints, an alias, a `/{path}` nested catch-all — is deliberate, not duplication. The
maintenance-trap concern is about a `[Controller, method]` binding copied to two places, not about one
invokable serving the paths a spec requires.