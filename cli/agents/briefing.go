package agents

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/library"
)

const BriefingBlock = "code-commandments briefing"

func Brief(root string, project config.Config) []error {
	var refused []error

	canon := InstructionsAt(filepath.Join(root, "AGENTS.md"), root)
	if err := canon.Inject(BriefingBlock, library.Brief(root, project)); err != nil {
		refused = append(refused, err)
	}

	for _, agent := range ForProject(project) {
		file := agent.InstructionsFile()
		if file == "" {
			continue
		}

		instructions := InstructionsAt(filepath.Join(root, file), root)
		if instructions.SameFileAs(canon) {
			continue
		}

		if err := instructions.Inject(agent.BlockName(), agent.Instructions(root)); err != nil {
			refused = append(refused, err)
		}
	}

	return refused
}
