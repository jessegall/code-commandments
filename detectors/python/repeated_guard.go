package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// RepeatedGuardDetector finds the same guard over an object's attributes written at two or more sites: a question the object should answer.
type RepeatedGuardDetector struct{}

func init() {
	detectors.Register(catalog.Python, RepeatedGuardDetector{})
}

// Sin is the sin the detector finds.
func (RepeatedGuardDetector) Sin() sins.Sin {
	return pysins.RepeatedGuard{}
}

// Find is every place the sin is committed.
func (RepeatedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	guards := py.In(codebase).
		WhereKind("BoolOp").
		Where(engine.As(py.Node.IsEvaluated)).
		Where(engine.As(py.Node.IsSubstantiveGuard)).
		Reject(engine.As(py.Node.IsAssignedValue)).
		Get()

	return flatten(engine.RecurringBuckets(guards, guardFingerprint, 2))
}

// guardFingerprint is the fingerprint a guard is grouped by.
func guardFingerprint(match engine.Match) (string, bool) {
	return py.Node{Match: match}.GuardFingerprint(), true
}

// flatten is every match of the groups, group by group.
func flatten(groups [][]engine.Match) []engine.Match {
	var matches []engine.Match
	for _, group := range groups {
		matches = append(matches, group...)
	}

	return matches
}
