<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Spatie;

use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class PageObjects extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/page-objects',
            tier: Tier::Mandatory,
            order: 5,
        );
    }

    public function title(): string
    {
        return "Page Objects — the Data class builds the whole page";
    }

    public function trigger(): string
    {
        return "How to write a PAGE OBJECT — the composed Spatie `Data` a controller returns for one page to render (it composes several nested Data slots and travels back in the response). Inject collaborators from the container with `#[FromContainer]` and ALWAYS `#[Hidden]` (an un-hidden service leaks into the generated TypeScript); never reach for `app()`/`resolve()`/a facade inside the object. Express each slot as a `#[Computed]` property hook (`public T \$x { get => … }`) seeded from the injected request/services, not a fat constructor of `\$this->x = …` assignments — except a `Lazy`/deferred slot or a local unwrapped once for reuse, which stay in the constructor. Shape a property's OUTPUT with a `#[WithTransformer]` / a custom `Transformer` when its serialized wire shape must differ from its PHP type (a value object rendered as a string, a domain type flattened for the frontend) — never a hand-rolled getter that reshapes an array. Read this BEFORE you write or review a `*Page`/view-model Data class, a controller that returns a Data payload, a `#[FromContainer]`/`#[Hidden]`/`#[Computed]`/`#[WithTransformer]` on a Data property, a page object's constructor, OR whenever a Data property's type needs a different serialized shape than its PHP type (a `Money`, a `Carbon`, an enum, any value object on the wire) and you're deciding how to transform it.";
    }

    public function intro(): string
    {
        return "A page object is a `Data` class that assembles one page's whole payload and hands it back to the
frontend. Let it build ITSELF: seed it from an id, pull its collaborators from the container (hidden), and
let each slot be a computed projection — not a constructor that imperatively fills field after field.";
    }

    public function summary(): string
    {
        return "the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A **page object** is the single composed `Data` a controller returns for one page to render. It is not a
leaf DTO: it *composes several nested Data slots* (a canvas, a list of rows, a set of menus) and it
*travels back in the response*. Those two facts are what make it a page object — and what make its
discipline different from an ordinary [`spatie-data`](../spatie-data/SKILL.md) value. A leaf DTO should
avoid `Lazy` and output transformers; a page object earns them. Same library, higher-order shape.

### Let it build itself from a seed

Give it a tiny named constructor — `EditorShell::for($workflowId)` → `::from(['workflowId' => $workflowId])` —
and let the framework pipeline assemble the rest. The controller stays a one-liner: it returns the page
object (a `Data` is `Responsable`) or hands it to the renderer. The *page* knows how to build the page.

### Inject collaborators from the container — hidden, always

A page object needs services to project its slots — a repository, a normaliser, a fleet of projectors, the
current request. Pull each from the container with **`#[FromContainer(SomeService::class)]`** on a promoted
property; never reach *out* with `app()`, `resolve()`, `App::make()`, or a facade inside the object. The
container injection is declarative, testable, and visible in the signature; a `app(X::class)` buried in a
getter is service location — the thing the container exists to remove.

**Every injected collaborator MUST carry `#[Hidden]`.** Miss it, and your `NodeCardProjector` becomes a
field on the frontend `EditorShell` type — a leak that is invisible until you read the generated `.d.ts`.
Inject hidden, or the service ships to the browser.

**Mind the TWO `#[Hidden]`s.** There are two unrelated attributes of that name:
`Spatie\LaravelData\Attributes\Hidden` drops a property from the **serialized payload**;
`Spatie\TypeScriptTransformer\Attributes\Hidden` drops it from the **generated TypeScript type**. LaravelData's
`#[Hidden]` alone keeps the service off the wire but the transformer *still generates it* into the `.d.ts`.
Rather than stamp both attributes on every injected service, run `commandments scaffold
--sin=injected-service-not-hidden` — it publishes a `HiddenAwareAttributedClassTransformer` (a thin
`AttributedClassTransformer` that also drops LaravelData-`#[Hidden]` properties). Register it in your
typescript-transformer config's `transformers` list, and one `#[Hidden]` covers both surfaces.

### Computed slots, not a fat constructor

The wrong shape is a constructor that imperatively fills every field:

```
$this->topBarCenter = $this->topBar->center();
$this->docks        = $this->dockProjector->project();
```

The right shape makes each slot a **computed property hook** that projects itself on demand:

```
#[Computed]
public array $topBarCenter { get => $this->topBar->center(); }
```

Now the class is self-describing: each field declares *what it is*, next to *how it is projected*, with no
assembly-line constructor to read top to bottom. Two mechanics to get right:

- A get-only hook is **re-evaluated on every read**. For a slot whose projection is expensive or must be
  stable, memoise it — `get => $this->rows ??= $this->resolveRows();` — so it computes **once**. That
  `??=` form is the default for a real projection; a pure, cheap derivation can stay un-memoised.
- Computed slots are **excluded from the input payload** (the framework computes them), so they never
  belong to construction — only injected seeds (the id, the `#[Hidden]` services) do.

**What legitimately STAYS in the constructor** — a slot the hook form would break:

- a **`Lazy` / deferred** slot (`Lazy::closure(...)`, an Inertia `DeferProp`/`MergeProp`): converting it to
  an eager `get` destroys the deferral the page relies on for partial reloads.
- a **local unwrapped once for reuse**: when several slots need the same intermediate (`$menus` built once,
  then read by both `topBarEnd` and `menus`), compute it once in the constructor rather than re-run the
  projector inside two hooks. A construction step that *feeds* the slots is not orchestration to hoist.

### Seed computed slots from the request

The clean way to make a page react to query/state: inject the request **hidden**, then let computed slots
read it. `#[Hidden] #[FromContainer(WarehouseShowRequest::class)] public WarehouseShowRequest $request` in
the signature, and `#[Computed] public string $movementWindow { get => $this->request->getMovementWindow(); }`
as a slot. The request is a seed; the slots are projections of it. No `request()` helper, no facade.

### Shape output with a transformer, not a hand-rolled getter

When a property's *serialized shape* must differ from its PHP type — a value object rendered as a string, a
domain type flattened for the wire — reach for **`#[WithTransformer(SomeTransformer::class)]`** on that
property. The wrong move is a computed getter that hand-builds the reshaped array:

```
// Wrong — an Order's fields hand-flattened into a wire array; the honest type is lost, and the same
// shape is copy-pasted onto every page that carries a price.
public array $priceInEuro { get => ['amount' => $this->order->priceInCents, 'currency' => $this->order->currency]; }

// Right — a real Money slot; a transformer owns the wire shape, and the TS type is declared to match it.
#[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
public readonly Money $priceInEuro;
```

A transformer is a tiny class implementing Spatie's `Transformer` —
`transform(DataProperty $property, mixed $value, TransformationContext $context): mixed` — returning the
serialized form (`$value->cents . ' ' . $value->code`, an array, whatever the frontend needs). Applied
per-property with `#[WithTransformer(X::class, ...args)]`, or registered as a global transformer in
`config/data.php` for a whole type (a `Money`, a `Carbon`). Keeping the transform in a transformer means
the property's PHP type stays honest and the same shaping is reusable across every page that carries a `Money`.

**A transformer changes the wire shape, but NOT the generated TypeScript.** The typescript-transformer
derives a property's TS type from its PHP type hint (`Money`), not from the transformer's output — so pair
the transformer with **`#[TypeScriptType('string')]`** (PHP-type syntax) or **`#[LiteralTypeScriptType(...)]`**
(raw TS, e.g. a reference to another generated type) declaring the transformed shape. Without it the frontend
type silently stays `Money` while the wire carries a string. (A built-in like `Carbon`→`string` is already
known to the generator; a custom value object is not — you must state it.)

(For a *leaf* DTO the [`spatie-data`](../spatie-data/SKILL.md) skill says to avoid output transformers; a
page object — the composed thing on the wire — is exactly where they earn their place.)
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            SpatieData::class => "the mechanics of a `Data` class this builds on — `::from()`, honest types, `#[DataCollectionOf]`; a page object is a Data used as a composed view-model.",
            LaravelIdioms::class => "owns the general 'no service location — inject through the container' rule; a page object's `#[FromContainer]` is that rule applied to a Data.",
            FixAtTheSource::class => "a page object is a boundary; build each slot where it is projected, not by threading half-built state through a constructor.",
        ];
    }
}
