package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/natural"
)

const mergeSinsShown = 5

func mergeTold(who, about, root string, marks []SinMark) string {
	if len(marks) == 0 {
		return ""
	}

	said := []string{who + about + " carries " + natural.Counted(len(marks), "sin") + ", each to fix at its source:"}

	for _, mark := range marks[:min(len(marks), mergeSinsShown)] {
		said = append(said, "  "+mark.ShownFrom(root))
	}

	if more := len(marks) - mergeSinsShown; more > 0 {
		said = append(said, "  and "+natural.Counted(more, "sin")+" more, which `"+binary.JudgingChanges(root)+"` lists")
	}

	return strings.Join(said, "\n")
}
