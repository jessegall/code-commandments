package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Offers(contract.CSharp,
		engine.Predicate{Name: "inherited", Says: "the member overrides or implements another, decided against the whole hierarchy", Holds: func(m engine.Match) bool { return Node{Match: m}.IsInherited() }},
		engine.Predicate{Name: "inOverride", Says: "it sits in a member that overrides or implements a contract, whose signature the contract decided", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinOverride() }},
		engine.Predicate{Name: "buildingAnObject", Says: "it feeds straight into an object being created in the same function", Holds: func(m engine.Match) bool { return Node{Match: m}.IsBuildingAnObject() }},
		engine.Predicate{Name: "inNamedConstructor", Says: "the function around it builds an instance of its own type", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinNamedConstructor() }},
	)
}
