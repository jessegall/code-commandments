package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

const leastQueryRepeats = 3

// RepeatedQueryChainDetector finds one Eloquent query chain written alike at three or more sites outside its model.
type RepeatedQueryChainDetector struct{}

func init() { detectors.Register(catalog.PHP, RepeatedQueryChainDetector{}) }

func (RepeatedQueryChainDetector) Sin() sins.Sin { return backendsins.RepeatedQueryChain{} }

func (RepeatedQueryChainDetector) Find(codebase *engine.Codebase) []engine.Match {
	chains := php.In(codebase).
		Where(engine.As(func(n laravelnode.Node) bool { return n.QueriedModel() != "" })).
		Reject(engine.As(func(n laravelnode.Node) bool { return n.QueriedModel() == php.EnclosingClassName(n.Match) })).
		Reject(engine.Match.IsTest).
		Get()

	var findings []engine.Match
	for _, group := range engine.RecurringBuckets(chains, chainShape, leastQueryRepeats) {
		if spansSeveralFunctions(group) {
			findings = append(findings, group...)
		}
	}

	return findings
}

func chainShape(chain engine.Match) (string, bool) {
	return php.LiteralHash(chain), true
}

func spansSeveralFunctions(group []engine.Match) bool {
	functions := map[string]bool{}
	for _, site := range group {
		functions[site.Scope()] = true
	}

	return len(functions) >= 2
}

func (RepeatedQueryChainDetector) CrossFile() {}
