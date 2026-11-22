package detectors

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
)

// TreeStanding is a rule that says for itself whether its answer for a file can turn on any file of the tree: a rule
// file, whose find decides it.
type TreeStanding interface {
	StandsOnTheTree() bool
}

// StandsOnTheTree says whether a rule's answer for a file can turn on any file of the tree.
func StandsOnTheTree(detector Detector) bool {
	standing, says := detector.(TreeStanding)

	return says && standing.StandsOnTheTree()
}

func ReadsBeyondOneFile(detector Detector) bool {
	wholeTree := StandsOnTheTree(detector)
	_, grouped := detector.(Grouped)

	return wholeTree || grouped || catalog.IsCrossFile(detector)
}

// RequiresBestDesign is a detector that may be a design smell: when it says so, a report that it is wrong must name the cleanest design the reporter can conceive.
type RequiresBestDesign interface {
	RequiresBestDesign() bool
}

// AdvisingDetector names the concrete source change a finding suggests.
type AdvisingDetector interface {
	AdviceFor(finding engine.Match) string
}
