package csharp

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DataClumpDetector finds the same three or more scalar parameters threaded through members of two or more types: one concept with no type of its own.
type DataClumpDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DataClumpDetector{})
}

// Sin is the sin the detector finds.
func (DataClumpDetector) Sin() sins.Sin {
	return cssins.DataClump{}
}

// Find is every place the sin is committed.
func (d DataClumpDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every function taking scalar values, keyed by the values it takes, with the type declaring it.
func (d DataClumpDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	matches := cs.In(codebase).
		WhereFunction().
		Where(engine.As(func(n cs.Node) bool { return len(n.ValueParamSignature()) > 0 })).
		Reject(engine.As(cs.Node.IsInherited)).
		Reject(engine.As(cs.Node.IsNamedConstructor)).
		Get()
	candidates := keyedBy(matches, d.GroupKey)
	for at, match := range matches {
		record := candidates[at].Record.(keyed)
		record.owner = cs.Node{Match: match}.Owner()
		candidates[at].Record = record
	}

	return candidates
}

// Decide is every function whose values two or more types take together.
func (DataClumpDetector) Decide(candidates []detectors.Candidate) []int {
	var findings []int
	for _, clump := range engine.Recurring(len(candidates), keyOf(candidates), 1) {
		owners := map[string]bool{}
		for _, at := range clump {
			owners[candidates[at].Record.(keyed).owner] = true
		}
		if len(owners) >= 2 {
			findings = append(findings, clump...)
		}
	}

	return findings
}

// GroupKey is the group a function recurs in: the scalar parameters it takes, each `type name`.
func (DataClumpDetector) GroupKey(match engine.Match) (string, bool) {
	signature := cs.Node{Match: match}.ValueParamSignature()

	return strings.Join(signature, ", "), len(signature) > 0
}
