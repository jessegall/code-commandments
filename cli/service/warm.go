package service

import (
	"slices"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/scan"
)

const warmedAtOnce = 200

func warm(held Bridge, project string, bridged bridge.Asker, console cli.Console) {
	files := filesOf(held, project)
	if len(files) == 0 {
		return
	}

	for group := range slices.Chunk(files, warmedTogether(held, len(files))) {
		if _, err := bridged.AskTallied(held.Request([]string{project}, group), func() {}); err != nil {
			console.Warn("The " + held.Label + " bridge could not be read into warm, so the first run pays for it: " + err.Error())

			return
		}
	}

	console.Say("The " + held.Label + " bridge is warm: " + natural.Counted(len(files), "file") + " of " + project + " read.")
}

func warmedTogether(held Bridge, files int) int {
	if held.WholeRoots {
		return files
	}

	return warmedAtOnce
}

func filesOf(held Bridge, project string) []string {
	settings, err := config.Load(project)
	if err != nil {
		return nil
	}

	roots, err := settings.WalkedRoots(project)
	if err != nil {
		return nil
	}

	return scan.Walk(roots, settings.Exclusions(project)).Files(held.Languages...)
}
