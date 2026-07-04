<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Laravel;

use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class RouteActions extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/route-actions',
            tier: Tier::Mandatory,
            order: 15,
        );
    }

    public function title(): string
    {
        return "Route Actions — one operation, one entry point";
    }

    public function trigger(): string
    {
        return "How a route action (controller method) earns its existence — it is a THIN seam that validates the request and delegates INTO the domain, and it is the ONLY way in to its operation. Never wrap another controller (a controller that forwards to another controller's action is a redundant entry point); never duplicate a sibling action's body; never wire two routes to the same action. Read this BEFORE you add a controller/route action, forward a request from one controller to another, copy an action body, or register a route.";
    }

    public function intro(): string
    {
        return "A route action is the thin boundary between an HTTP request and your domain. Its job is to
translate the request and hand off — once. Two routes that answer the same operation, or two controllers
that do the same thing, are duplication at the boundary: one operation deserves exactly one way in.";
    }

    public function summary(): string
    {
        return "route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
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
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            FixAtTheSource::class => "a redundant controller is duplication at the boundary — hoist the shared work to the one place it belongs (a service), then both routes call it.",
            LaravelIdioms::class => "controllers/routes are Laravel's HTTP edge; this is the discipline for keeping that edge thin and single.",
        ];
    }
}
