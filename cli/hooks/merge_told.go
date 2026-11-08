package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/natural"
)

func mergeTold(who, about, root string, marks []SinMark) string {
	if len(marks) == 0 {
		return ""
	}

	said := []string{who + about + " carries " + natural.Counted(len(marks), "sin") + ", each to fix at its source:"}

	for _, mark := range marks {
		said = append(said, "  "+mark.ShownFrom(root))
	}

	return strings.Join(said, "\n")
}
