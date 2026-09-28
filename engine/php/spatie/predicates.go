package spatie

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Offers(contract.PHP,
		engine.Predicate{Name: "inDataClass", Says: "it sits in a Spatie Data class", Holds: func(m engine.Match) bool { return Node{Match: m}.IsDataClass() }},
		engine.Predicate{Name: "inPageObject", Says: "it sits in a Data class that is a page object", Holds: func(m engine.Match) bool { return Node{Match: m}.IsPageObject() }},
	)
}
