// Package packages holds what the PHP packages a project uses excuse: each package registers, under an exemption tag, the types and methods of its own boundary that a general rule leaves alone.
package packages

// Tag names one kind of exemption a package can register and a detector can honour.
type Tag struct {
	Slug        string
	Description string
}

var (
	ArrayCallbackContract = Tag{"array-callback-contract", "The framework requires this method's callback to return an array."}
	ArrayInputContract    = Tag{"array-input-contract", "This framework boundary takes arrays carrying values in its declared input format."}
	ArrayReturnContract   = Tag{"array-return-contract", "The framework requires this hook to return an array."}
	ArrayReturning        = Tag{"array-returning", "A class whose whole job is handing the framework arrays (a FormRequest, an MCP tool) — its array returns are contractual, exempt from array-return-bag (class-level)."}
	Association           = Tag{"association", "A call or attribute declaring one end of a two-way association (an ORM relation like `hasOne(Picker::class)`, a binding like `#[ObservedBy(OrderObserver::class)]`) — the framework requires both ends to name each other, so the reference carries no dependency direction and cannot close a cycle."}
	Boundary              = Tag{"boundary", "A framework entry point (an HTTP/RPC request) — exempt from feature-envy (don't move behaviour onto it) and pass-the-object (a method taking one may unpack its input)."}
	CompositionRoot       = Tag{"composition-root", "A provider or framework construction hook that wires config into values without constructor injection."}
	ContractMethod        = Tag{"contract-method", "A framework-mandated method (`rules`/`schema`/`casts`/`Guard::user`/`Arrayable::toArray`) whose signature the framework dictates — exempt from near-duplicate, array-return-bag and de-nulled-finder."}
	ControlSignal         = Tag{"control-signal", "A Throwable thrown purely to steer control flow, not to report a failure (an engine's break/stop signal) — catching it with an empty body IS the semantics, so it is exempt from swallow-catch."}
	DynamicMembers        = Tag{"dynamic-members", "A type whose framework supplies members at runtime, such as an Eloquent model with database attributes and query methods."}
	NoContainer           = Tag{"no-container", "A type the framework instantiates itself, no container/DI (an Eloquent cast, a Spatie DataPipe/Cast) — a loose array parameter and per-call container reach are the framework's convention; exempt from array-bag and container-reach."}
	ValidationResponse    = Tag{"validation-response", "A framework factory builds a validation response from field messages and has no parameter for the caught failure."}
)

var ArrayCallbackInputContract = Tag{"array-callback-input-contract", "The framework supplies an array to this method's callback."}

var Tags = []Tag{ArrayCallbackContract, ArrayCallbackInputContract, ArrayInputContract, ArrayReturnContract, ArrayReturning, Association, Boundary, CompositionRoot, ContractMethod, ControlSignal, DynamicMembers, NoContainer, ValidationResponse}
