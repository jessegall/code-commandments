// Package service keeps a language's bridge running for a project, answering each run of the tool over a socket named for the bridge and the project: the C# bridge, so a judge compiles against references it has already loaded, and the Python bridge, so each edit is typed by a mypy session already warm instead of a process started for it.
package service

import (
	"os"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Bridge is the bridge a service keeps up, which cli/scan declares beside the one funnel every bridge is reached through: the languages it reads, the words its verbs use, and how it is started.
type Bridge = scan.Bridge

func here(b Bridge) (string, bool, error) {
	project, named := workspace.JournalProject()
	if !named {
		cwd, _ := os.Getwd()
		project = workspace.ProjectRoot(cwd)
	}

	settings, err := config.Load(project)
	if err != nil {
		return project, false, err
	}

	return project, b.HeldIn(func(language source.Language) bool { return settings.Holds(project, language) }), nil
}
