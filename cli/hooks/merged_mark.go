package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var mergedLegend = &state.Legend{
	About: "Code-commandments — the last commit of each branch whose merged work was judged, so a merge delivered twice is judged once and a merge whose delivery was lost is picked up by the next one.",
	List:  "one branch per line: the branch, a tab, then the commit up to which its merged work has been judged",
	Safe:  "the next merge on each branch is judged from the commit the journal names rather than from where we left off",
}

// Merged is the record of how far each branch's merged work has been judged.
type Merged struct {
	file state.File
}

func MergedIn(space workspace.Workspace) Merged {
	return Merged{state.At(space.Path(".merged-judged"), mergedLegend)}
}

func (m Merged) Upto(branch string) string {
	for _, item := range m.file.Read().Items() {
		named, commit, split := strings.Cut(item, "\t")
		if split && named == branch {
			return commit
		}
	}

	return ""
}

func (m Merged) Judged(branch, commit string) error {
	read := m.file.Read()

	var items []string
	for _, item := range read.Items() {
		if named, _, split := strings.Cut(item, "\t"); !split || named != branch {
			items = append(items, item)
		}
	}

	return m.file.Write(read.WithItems(append(items, branch+"\t"+commit)))
}
