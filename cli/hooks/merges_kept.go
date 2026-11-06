package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var mergesLegend = &state.Legend{
	About: "Code-commandments — what the last merges brought in, kept where a person can read it, because the journal drops a line it has no agent to tell and a merge run at a terminal has nobody.",
	List:  "one merge per line: the branch, a tab, the commit it ended at, a tab, then what was found with its newlines written as \\n",
	Safe:  "what past merges brought in is forgotten; the next merge says what it finds",
}

const mergesKept = 20

// Merges is what the last merges brought in, kept for a person to read where nobody was told.
type Merges struct {
	file state.File
}

func MergesIn(space workspace.Workspace) Merges {
	return Merges{state.At(space.Cache(".merges-found"), mergesLegend)}
}

func (m Merges) Keeps(branch, commit, said string) error {
	read := m.file.Read()

	items := append(read.Items(), branch+"\t"+commit+"\t"+strings.ReplaceAll(said, "\n", `\n`))
	if len(items) > mergesKept {
		items = items[len(items)-mergesKept:]
	}

	return m.file.Write(read.WithItems(items))
}

func (m Merges) Found() []string {
	var said []string
	for _, item := range m.file.Read().Items() {
		said = append(said, strings.ReplaceAll(item, `\n`, "\n"))
	}

	return said
}
