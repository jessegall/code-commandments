package frontend

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

func init() {
	for _, language := range []contract.Language{contract.Vue, contract.TypeScript} {
		engine.Offers(language,
			engine.Predicate{Name: "optional", Says: "a field or parameter may be missing: written `x?`, or typed to admit null or undefined", Holds: func(m engine.Match) bool { return typescript.Of(m).IsOptional() }},
			engine.Predicate{Name: "absence", Says: "it is the literal null or undefined", Holds: func(m engine.Match) bool { return typescript.Of(m).IsAbsence() }},
		)
	}
}
