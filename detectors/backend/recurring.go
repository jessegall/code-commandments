package backend

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// callSites is every named method send, static call and new of a named class: the calls a callee can be resolved for.
func callSites(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).Where(func(m engine.Match) bool {
		switch m.Kind() {
		case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall":
			return m.Child("name").Kind() == "Identifier"
		case "Expr_New":
			return (php.Node{Match: m}).NewClassName() != ""
		}

		return false
	}).Get()
}

// recurring is every candidate whose fingerprint recurs at least minimum times, group by group, keeping the groups
// the qualifier accepts.
func recurring(candidates []engine.Match, fingerprint func(engine.Match) string, minimum int, qualifies func([]engine.Match) bool) []engine.Match {
	var findings []engine.Match
	key := func(m engine.Match) (string, bool) {
		read := fingerprint(m)

		return read, read != ""
	}
	for _, group := range engine.RecurringBuckets(candidates, key, minimum) {
		if qualifies(group) {
			findings = append(findings, group...)
		}
	}

	return findings
}
