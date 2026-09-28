package laravel

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Predicates(contract.PHP,
		engine.Predicate{Name: "facadeCall", Says: "it is a static call on a Laravel facade", Holds: func(m engine.Match) bool { return Node{Match: m}.IsFacadeCall() }},
	)
}
