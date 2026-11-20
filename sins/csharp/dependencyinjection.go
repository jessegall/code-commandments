package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// ContainerReach records a lookup of a dependency a class can receive at construction.
type ContainerReach struct{}

// OrphanedBinding records a configured service that has no production consumer.
type OrphanedBinding struct{}

func init() {
	sins.Register(catalog.CSharp, ContainerReach{})
	sins.Register(catalog.CSharp, OrphanedBinding{})
}

func (ContainerReach) Definition() sins.Definition {
	return sins.Definition{Name: "csharp-container-reach", Skill: skills.DependencyDirection{}, Description: "A class locates a service through IServiceProvider", Rule: "Declare the dependency in the constructor.", Suggestion: "Take the service directly and leave lookup to the composition root."}
}

func (OrphanedBinding) Definition() sins.Definition {
	return sins.Definition{Name: "csharp-orphaned-binding", Skill: skills.DependencyDirection{}, Description: "A registered service has no production consumer", Rule: "Remove registrations for services no production code uses.", Suggestion: "Remove the unused registration."}
}
