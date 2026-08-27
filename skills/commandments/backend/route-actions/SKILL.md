---
name: commandments-backend-route-actions
description: "How a route action (controller method) earns its existence — it is a THIN seam that validates the request and delegates INTO the domain, and it is the ONLY way in to its operation. Never wrap another controller (a controller that forwards to another controller's action is a redundant entry point); never duplicate a sibling action's body; never wire two routes to the same action. Read this BEFORE you add a controller/route action, forward a request from one controller to another, copy an action body, or register a route."
---

# Route Actions — one operation, one entry point

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A route action is the thin boundary between an HTTP request and your domain. Its job is to
> translate the request and hand off — once. Two routes that answer the same operation, or two controllers
> that do the same thing, are duplication at the boundary: one operation deserves exactly one way in.

## The principle

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

## Rules

- [ ] One operation, one implementation. A boundary translates its own protocol and calls the shared application service; it does not re-spell the operation.
      _Hoist the shared sequence into one application service and have both faces call it._
- [ ] The route-name vocabulary is a CLOSED set: every name looked up must be a name some route registers. Renaming a route means renaming its references in the same breath.
      _Point the lookup at the registered name, or register the route the name promises._
- [ ] Register a `[Controller, method]` action once; a second URL onto the same handler is a maintenance trap (names, middleware, constraints drift). An invokable controller mapped to several canonical URLs is fine.
      _Keep one route; if a second URL is truly needed, make it a redirect, or an invokable controller._
- [ ] One operation, one entry point — collapse duplicate thin actions to a single action (or two routes onto one), with the work in the shared service.
      _Delete the duplicate action and point its route at the surviving one (or a redirect)._
- [ ] A route action delegates INTO the domain (a service/action class), never sideways into another controller.
      _Extract the shared work into a service both routes call, or point the route at the real action and delete the wrapper._

## Worked example

### boundary-duplicated-operation

The same domain operation hand-rolled at two DIFFERENT entry boundaries (a console command and an MCP tool, a controller and a command) — one operation with two implementations that drift

```php
----------[ Bad ]----------

public function handle(string $sku, LabelRenderer $renderer, LabelQueue $queue, PrintLog $log): string
{
    $job = $queue->push($renderer->render($sku));

    $log->record($job);

    return $this->answer($job);
}

----------[ Good ]----------

// in Shop\Mcp\PrintLabelTool
// The FIX: render-queue-record is hoisted into `LabelPrinting`, the ONE home for the operation, and
// every face calls it. What is left at this boundary is the only work that is genuinely its own —
// translating the protocol and shaping the answer an agent reads.

public function handleDelegating(string $sku, LabelPrinting $printing): string
{
    return $this->answer($printing->print($sku));
}

// The one home for "print a label" — the shared service every face should call.

final class LabelPrinting
{
    public function __construct(
        private readonly LabelRenderer $renderer,
        private readonly LabelQueue $queue,
        private readonly PrintLog $log,
    ) {}

    public function print(string $sku): string
    {
        $jobId = $this->queue->push($this->renderer->render($sku));

        $this->log->record($jobId);

        return $jobId;
    }
}
```

The other 4 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/route-actions` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `boundary-duplicated-operation`, `dangling-route-name`, `duplicate-route`, `duplicate-route-action`, `route-delegates-to-controller`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 5 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — a redundant controller is duplication at the boundary — hoist the shared work to the one place it belongs (a service), then both routes call it.
- [`backend/laravel-idioms`](../laravel-idioms/SKILL.md) — controllers/routes are Laravel's HTTP edge; this is the discipline for keeping that edge thin and single.
