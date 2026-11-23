package agents

import (
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const BriefingBlock = "code-commandments briefing"

const journalInstructions = "JOURNAL.md"

// BriefingFile is the one file the tool owns in a project's root and writes whole on every sync: the briefing, and what
// each agent the project uses calls its parts. An agent's own instruction file brings it in with a single line.
const BriefingFile = "COMMANDMENTS.md"

const writtenBySync = "<!-- Written whole by `commandments sync` on every run: change nothing here. -->"

const readsTheBriefing = "Read `" + BriefingFile + "` before you write code here: it holds the disciplines this project's code is judged by."

func Brief(root string, project config.Config) []error {
	var refused []error
	briefedByTheJournal := journalBriefs(root)

	place := func(file Instructions, line, formerBlock string) {
		var err error
		if briefedByTheJournal {
			err = file.Withdraw(line, formerBlock)
		} else {
			err = file.Hold(line, formerBlock)
		}

		if err != nil {
			refused = append(refused, err)
		}
	}

	briefing := library.Brief(root, project)
	canon := InstructionsAt(filepath.Join(root, "AGENTS.md"), root)
	place(canon, readsTheBriefing, BriefingBlock)

	for _, agent := range ForProject(project) {
		briefing += ownPartOf(agent.Instructions(root))
		file := agent.InstructionsFile()
		if file == "" {
			continue
		}

		instructions := InstructionsAt(filepath.Join(root, file), root)
		if instructions.SameFileAs(canon) {
			continue
		}

		place(instructions, agent.BriefingLine(), agent.FormerBlock())
	}

	if err := writtenBriefing(root, briefing, !briefedByTheJournal); err != nil {
		refused = append(refused, err)
	}

	return refused
}

func ownPartOf(instructions string) string {
	if strings.TrimSpace(instructions) == "" {
		return ""
	}

	return "\n\n" + strings.TrimSpace(instructions)
}

func writtenBriefing(root, briefing string, kept bool) error {
	path := filepath.Join(root, BriefingFile)
	held, err := os.ReadFile(path)
	if err == nil && !strings.HasPrefix(string(held), writtenBySync) {
		return &Refused{path + " is a file of your own, so the briefing was not written over it: move it, and run sync again."}
	}
	wanted := writtenBySync + "\n\n" + strings.TrimSpace(briefing) + "\n"
	switch {
	case !kept && err == nil:
		return os.Remove(path)
	case !kept || string(held) == wanted:
		return nil
	}
	if atomic.Write(path, wanted) != nil {
		return &Refused{path + " could not be written — left as it was."}
	}

	return nil
}

func journalBriefs(root string) bool {
	if !workspace.At(root, "").IsJournalDriven() {
		return false
	}

	return walk.Exists(filepath.Join(root, journalInstructions))
}
