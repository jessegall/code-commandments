package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// LoneImplementerInterface finds an interface has only one local implementation.
type LoneImplementerInterface struct{}

func (LoneImplementerInterface) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereTypeDeclaration().Reject(engine.Match.IsTest).Where(engine.Match.IsLoneAbstraction).Get()
}

func (LoneImplementerInterface) Sin() Text {
	return Text{Name: "lone-implementer-interface", Skill: skills.Dependencies{}, Description: "An interface has only one local implementation", Rule: "Use the implementation directly unless the interface is a boundary.", Suggestion: "Use the implementation directly unless the interface is a boundary."}
}

func (LoneImplementerInterface) CrossFile() {}

// ConcreteCrossModuleDependency finds a constructor takes another module's concrete service.
type ConcreteCrossModuleDependency struct{}

func (ConcreteCrossModuleDependency) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Parameter).Reject(engine.Match.IsTest).Where(engine.Match.InjectsAnotherModulesConcreteService).Get()
}

func (ConcreteCrossModuleDependency) Sin() Text {
	return Text{Name: "concrete-cross-module-dependency", Skill: skills.Dependencies{}, Description: "A constructor takes another module's concrete service", Rule: "Take a contract for the cross-module dependency.", Suggestion: "Take a contract for the cross-module dependency."}
}

func (ConcreteCrossModuleDependency) CrossFile() {}

// MiddleMan finds a type forwards its methods to one field.
type MiddleMan struct{}

func (MiddleMan) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereTypeDeclaration().Where(engine.Match.IsMiddleMan).Get()
}

func (MiddleMan) Sin() Text {
	return Text{Name: "middle-man", Skill: skills.Dependencies{}, Description: "A type forwards its methods to one field", Rule: "Give callers the type that owns the operations.", Suggestion: "Give callers the type that owns the operations."}
}

func (MiddleMan) CrossFile() {}

// RefusedBequest finds an override only throws.
type RefusedBequest struct{}

func (RefusedBequest) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.RefusesInheritedMethod).Get()
}

func (RefusedBequest) Sin() Text {
	return Text{Name: "refused-bequest", Skill: skills.Dependencies{}, Description: "An override only throws", Rule: "Remove the obligation or choose a type that supports it.", Suggestion: "Remove the obligation or choose a type that supports it."}
}

func (RefusedBequest) CrossFile() {}

// HandRolledRegistry finds a type repeats a checked keyed store.
type HandRolledRegistry struct{}

func (HandRolledRegistry) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereTypeDeclaration().Where(engine.Match.HandRollsRegistry).Get()
}

func (HandRolledRegistry) Sin() Text {
	return Text{Name: "hand-rolled-registry", Skill: skills.Dependencies{}, Description: "A type repeats a checked keyed store", Rule: "Use the project's registry abstraction for keyed storage.", Suggestion: "Use the project's registry abstraction for keyed storage."}
}

func (HandRolledRegistry) CrossFile() {}

// TrainWreck finds a chain reaches through three local types.
type TrainWreck struct{}

func (TrainWreck) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ReachesThroughOwnedTypes).Get()
}

func (TrainWreck) Sin() Text {
	return Text{Name: "train-wreck", Skill: skills.Dependencies{}, Description: "A chain reaches through three local types", Rule: "Ask the nearest owner for the behavior the caller needs.", Suggestion: "Ask the nearest owner for the behavior the caller needs."}
}

func (TrainWreck) CrossFile() {}

// UndeclaredMember finds a local type lacks the member being read.
type UndeclaredMember struct{}

func (UndeclaredMember) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ReadsUndeclaredMember).Reject(engine.Match.IsTest).Get()
}

func (UndeclaredMember) Sin() Text {
	return Text{
		Name:        "undeclared-member",
		Skill:       skills.Dependencies{},
		Description: "A member access the receiver's declared type neither declares nor inherits",
		Rule:        "Read what the declared type guarantees. When only some implementers carry the member, give them a contract that declares it and type the receiver by that contract.",
		Suggestion:  "Declare the member on the carrying types' contract and use that contract at the access.",
	}
}

func (UndeclaredMember) CrossFile() {}

// UnusedPrivateMember finds a private member has no readers.
type UnusedPrivateMember struct{}

func (UnusedPrivateMember) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsUnusedPrivateMember).Get()
}

func (UnusedPrivateMember) Sin() Text {
	return Text{Name: "unused-private-member", Skill: skills.Dependencies{}, Description: "A private member has no readers", Rule: "Remove the unused private member.", Suggestion: "Remove the unused private member."}
}

func (UnusedPrivateMember) CrossFile() {}

// UnreadCollaborator finds a collaborator is stored and never read.
type UnreadCollaborator struct{}

func (UnreadCollaborator) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsUnreadCollaborator).Get()
}

func (UnreadCollaborator) Sin() Text {
	return Text{Name: "unread-collaborator", Skill: skills.Dependencies{}, Description: "A collaborator is stored and never read", Rule: "Remove the unused collaborator or use it where it is needed.", Suggestion: "Remove the unused collaborator or use it where it is needed."}
}

func (UnreadCollaborator) CrossFile() {}

// UselessPropertyHook finds a getter reads no state on its object.
type UselessPropertyHook struct{}

func (UselessPropertyHook) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsStateFreeGetter).Get()
}

func (UselessPropertyHook) Sin() Text {
	return Text{Name: "useless-property-hook", Skill: skills.Dependencies{}, Description: "A getter reads no state on its object", Rule: "Use a constant or a named operation.", Suggestion: "Use a constant or a named operation."}
}

func (UselessPropertyHook) CrossFile() {}

// GuardBesideTransition finds a caller checks another object's state before telling it to act.
type GuardBesideTransition struct{}

func (rule GuardBesideTransition) Find(codebase *engine.Codebase) []engine.Match {
	return rule.FindInBranches(codebase.WhereIs(engine.Branch))
}

func (GuardBesideTransition) FindInBranches(branches *engine.Query) []engine.Match {
	return branches.
		Where(engine.Match.GuardsAnotherObjectsTransition).
		Reject(engine.Match.IsTest).
		Get()
}

func (GuardBesideTransition) Sin() Text {
	return Text{Name: "guard-beside-transition", Skill: skills.Dependencies{}, Description: "A caller checks another object's state before telling it to act", Rule: "Put the guard inside the transition that owns the state.", Suggestion: "Put the guard inside the transition that owns the state."}
}

func (GuardBesideTransition) CrossFile() {}
