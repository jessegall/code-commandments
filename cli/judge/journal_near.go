package judge

import (
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const journalVariables = "JOURNAL_"

func aJournalNear(folder string, space workspace.Workspace) (string, bool) {
	for _, variable := range os.Environ() {
		if strings.HasPrefix(variable, journalVariables) {
			named, _, _ := strings.Cut(variable, "=")

			return "this run carries " + named + ", which only a journal sets", true
		}
	}

	for above := filepath.Clean(folder); ; above = filepath.Dir(above) {
		if info, err := os.Stat(filepath.Join(above, ".journal")); err == nil && info.IsDir() {
			return above + " holds a journal", true
		}

		if filepath.Dir(above) == above {
			break
		}
	}

	if space.IsJournalDriven() {
		return "this project's journal runs the code-commandments plugin", true
	}

	if warm.EverServed(folder) {
		return "a judging service has served this project on this machine before", true
	}

	return "", false
}
