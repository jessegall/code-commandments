package service

import (
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func warm(held Bridge, project string, bridged bridge.Asker, console cli.Console) {
	files := filesOf(held, project)
	if len(files) == 0 {
		return
	}

	if _, err := bridged.AskTallied(held.Request([]string{project}, files), func() {}); err != nil {
		console.Warn("The " + held.Label + " bridge could not be read into warm, so the first run pays for it: " + err.Error())

		return
	}

	console.Say("The " + held.Label + " bridge is warm: " + natural.Counted(len(files), "file") + " of " + project + " read.")
}

func filesOf(held Bridge, project string) []string {
	settings, err := config.Load(project)
	if err != nil {
		return nil
	}

	roots, err := config.DeclaredRoots(project)
	if err != nil {
		return nil
	}

	return scan.Walk(roots, source.Under(project, settings.Excluded)).Files(held.Languages...)
}
