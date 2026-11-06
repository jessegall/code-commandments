package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/git"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var mergedLegend = &state.Legend{
	About: "Code-commandments — the last commit of each branch whose merged work was judged, so a merge delivered twice is judged once and a merge whose delivery was lost is picked up by the next one. It outlives a session on purpose: a merge made between sessions is exactly the case it exists for.",
	List:  "one branch per line: the branch, a tab, then the commit up to which its merged work has been judged",
	Safe:  "the next merge on each branch is judged from the commit the journal names rather than from where we left off",
}

// Merged is the record of how far each branch's merged work has been judged.
type Merged struct {
	file state.File
}

func MergedIn(space workspace.Workspace) Merged {
	return Merged{state.At(space.Cache(".merged-judged"), mergedLegend)}
}

func (m Merged) UptoUsable(root, branch, now string) (string, Narrowed) {
	mark := m.Upto(branch)
	if mark == "" {
		return "", ""
	}

	if !git.Holds(root, mark) {
		return "", markIsGone
	}

	if !git.IsAncestor(root, mark, now) {
		return "", markIsApart
	}

	return mark, ""
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

const (
	markIsGone  Narrowed = "the commit we last judged this branch up to is no longer in it, so what came in since cannot be read from it"
	markIsApart Narrowed = "the commit we last judged this branch up to is not an ancestor of what it is now, which is what a reset or a rewritten history leaves behind"
)
