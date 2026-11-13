package agents

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const BriefingBlock = "code-commandments briefing"

const journalInstructions = "JOURNAL.md"

func Brief(root string, project config.Config) []error {
	var refused []error

	briefedByTheJournal := journalBriefs(root)

	place := func(file Instructions, name, body string) {
		var err error
		if briefedByTheJournal {
			err = file.Withdraw(name)
		} else {
			err = file.Inject(name, body)
		}

		if err != nil {
			refused = append(refused, err)
		}
	}

	canon := InstructionsAt(filepath.Join(root, "AGENTS.md"), root)
	place(canon, BriefingBlock, library.Brief(root, project))

	for _, agent := range ForProject(project) {
		file := agent.InstructionsFile()
		if file == "" {
			continue
		}

		instructions := InstructionsAt(filepath.Join(root, file), root)
		if instructions.SameFileAs(canon) {
			continue
		}

		place(instructions, agent.BlockName(), agent.Instructions(root))
	}

	return refused
}

func journalBriefs(root string) bool {
	if !workspace.At(root, "").IsJournalDriven() {
		return false
	}

	return walk.Exists(filepath.Join(root, journalInstructions))
}
