package php

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Offers(contract.PHP,
		engine.Predicate{Name: "constructor", Says: "it declares a constructor", Holds: func(m engine.Match) bool { return Node{Match: m}.IsConstructorDeclaration() }},
		engine.Predicate{Name: "coalesce", Says: "it is a `??` expression", Holds: func(m engine.Match) bool { return Node{Match: m}.IsCoalesce() }},
		engine.Predicate{Name: "returnedValue", Says: "it is the value a return statement returns", Holds: func(m engine.Match) bool { return Node{Match: m}.IsReturnedValue() }},
		engine.Predicate{Name: "typeNarrowingGuard", Says: "it is an outermost `&&` of two or more instanceof checks", Holds: func(m engine.Match) bool { return Node{Match: m}.IsTypeNarrowingGuard() }},
		engine.Predicate{Name: "inNamedConstructor", Says: "the function around it builds an instance of its own class", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinNamedConstructor() }},
	)
}
