package python

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Offers(contract.Python,
		engine.Predicate{Name: "constructor", Says: "it is a class's __init__", Holds: func(m engine.Match) bool { return Node{Match: m}.IsConstructorDeclaration() }},
		engine.Predicate{Name: "evaluated", Says: "it is an expression the code evaluates, outside every type annotation", Holds: func(m engine.Match) bool { return Node{Match: m}.IsEvaluated() }},
		engine.Predicate{Name: "returnedValue", Says: "it is what a return statement returns", Holds: func(m engine.Match) bool { return Node{Match: m}.IsReturnedValue() }},
		engine.Predicate{Name: "typeNarrowingGuard", Says: "it is an outermost `and` of two or more isinstance checks", Holds: func(m engine.Match) bool { return Node{Match: m}.IsTypeNarrowingGuard() }},
		engine.Predicate{Name: "inNamedConstructor", Says: "it sits in a named constructor, where loose data becomes the class", Holds: func(m engine.Match) bool { return Node{Match: m}.IsWithinNamedConstructor() }},
	)
}
