package hooks

import (
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Lanes is `lanes`: what the last merges and hand-ins brought in, read from the project's own state, since the
// journal drops a line it has no agent to tell and a merge run at a terminal has nobody.
type Lanes struct{}

func (Lanes) Names() []string {
	return []string{"lanes"}
}

func (Lanes) Help() help.Help {
	return help.Of("Say what the last merges and hand-ins brought in, as the project keeps it.").
		Form("lanes", "what the last merges and hand-ins brought in").
		Note("It reads the project's own state, so it answers with nothing running: the journal drops a line it has " +
			"no agent to tell, and a merge run at a terminal has nobody.").
		In(help.Hooks)
}

func (Lanes) Run(_ *cli.Input, console cli.Console) (int, error) {
	said := mergesSaidIn(workspace.ProjectRoot(cwdOf()))
	if said == "" {
		console.Say("No merge or hand-in has brought anything in that nobody was told.")

		return 0, nil
	}

	console.Say(strings.TrimPrefix(said, "\n"))

	return 0, nil
}

func mergesSaidIn(root string) string {
	found := MergesIn(workspace.At(root, "")).Found()
	if len(found) == 0 {
		return ""
	}

	said := []string{"", "What the last " + strconv.Itoa(len(found)) + " merge(s) and hand-in(s) brought in, kept because a delivery with no agent has nobody to tell:"}
	for _, each := range found {
		branch, rest, _ := strings.Cut(each, "\t")
		_, words, _ := strings.Cut(rest, "\t")
		said = append(said, "  "+branch+": "+strings.ReplaceAll(words, "\n", "\n    "))
	}

	return strings.Join(said, "\n")
}
