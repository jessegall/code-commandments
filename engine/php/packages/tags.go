// Package packages holds what the PHP packages a project uses excuse: each package registers, under an exemption
// tag, the types and methods of its own boundary that a general rule leaves alone.
package packages

// Tag names one kind of exemption a package can register and a detector can honour.
type Tag struct {
	Slug        string
	Description string
}

// The tags the tool ships.
var (
	ArrayReturning  = Tag{"array-returning", "A class whose whole job is handing the framework arrays (a FormRequest, an MCP tool) — its array returns are contractual, exempt from array-return-bag (class-level)."}
	Association     = Tag{"association", "A call or attribute declaring one end of a two-way association (an ORM relation like `hasOne(Picker::class)`, a binding like `#[ObservedBy(OrderObserver::class)]`) — the framework requires both ends to name each other, so the reference carries no dependency direction and cannot close a cycle."}
	Boundary        = Tag{"boundary", "A framework entry point (an HTTP/RPC request) — exempt from feature-envy (don't move behaviour onto it) and pass-the-object (a method taking one may unpack its input)."}
	CompositionRoot = Tag{"composition-root", "A service provider's register/boot — the composition root where config() is wired into typed objects; a provider can't inject its own config, so config() reads here are exempt from config-read."}
	ContractMethod  = Tag{"contract-method", "A framework-mandated method (`rules`/`schema`/`casts`/`Guard::user`) whose signature the framework dictates — exempt from near-duplicate, array-return-bag and de-nulled-finder."}
	ControlSignal   = Tag{"control-signal", "A Throwable thrown purely to steer control flow, not to report a failure (an engine's break/stop signal) — catching it with an empty body IS the semantics, so it is exempt from swallow-catch."}
	NoContainer     = Tag{"no-container", "A type the framework instantiates itself, no container/DI (an Eloquent cast, a Spatie DataPipe/Cast) — a loose array parameter and per-call container reach are the framework's convention; exempt from array-bag and container-reach."}
)

// Tags is every tag the tool ships, by slug.
var Tags = []Tag{ArrayReturning, Association, Boundary, CompositionRoot, ContractMethod, ControlSignal, NoContainer}

// Tagged is the shipped tag with the slug.
func Tagged(slug string) (Tag, bool) {
	for _, tag := range Tags {
		if tag.Slug == slug {
			return tag, true
		}
	}

	return Tag{}, false
}
