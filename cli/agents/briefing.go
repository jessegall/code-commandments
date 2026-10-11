package agents

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/library"
)

// BriefingBlock names the block the brief is kept in, in AGENTS.md: the canon itself is the map skill, loadable,
// so the instruction file carries only what must be read before any of it.
const BriefingBlock = "code-commandments briefing"

// Brief writes what a project is told into the files its agents read: the briefing's own block in AGENTS.md, and
// each kept agent's block in the file it reads, skipped where that file IS AGENTS.md. It writes nothing else —
// publishing the skills, linking them, the .gitignore rules and the hooks stay sync's — so whatever refreshes a
// project's instructions can refresh only those, and a refusal from one file never stops the next. Every refusal
// comes back for the caller to say; the file it names is as it was.
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
