package shared

import (
	"github.com/jessegall/code-commandments/engine"
)

// RecurringRule finds occurrences by a key that can be compared between separately checked files.
type RecurringRule interface {
	GroupedRule
	Occurrences(codebase *engine.Codebase) []engine.Match
}

// RecurringDetector judges occurrences together even when their trees arrive separately.
type RecurringDetector struct {
	GroupedDetector
	recurring RecurringRule
}

