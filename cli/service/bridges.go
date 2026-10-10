// Package service keeps a language's bridge running for a project, answering each run of the tool over a socket named
// for the bridge and the project: the C# bridge, so a judge compiles against references it has already loaded, and
// the Python bridge, so each edit is typed by a mypy session already warm instead of a process started for it.
package service

import (
	"os"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Bridge is a bridge the journal may keep up for a project: the name its socket and its service carry, the word its
// has- verb uses, the language it reads, as a reader names it, and the command that starts it.
type Bridge struct {
	Kind     string
	Word     string
	Language source.Language
	Label    string
	command  func(project string) ([]string, error)
	notice   func() string
}

// CSharp is the C# bridge, Roslyn, kept up as the plugin's roslyn service.
var CSharp = Bridge{Kind: "roslyn", Word: "csharp", Language: source.CSharp, Label: "C#", command: func(project string) ([]string, error) {
	return bridge.Roslyn(project)
}, notice: bridge.RoslynNotice}

// Python is the Python bridge, mypy, kept up as the plugin's mypy service.
var Python = Bridge{Kind: "mypy", Word: "python", Language: source.Python, Label: "Python", command: func(string) ([]string, error) {
	return bridge.Mypy()
}, notice: func() string { return "" }}

// here is the project the journal runs it for, else the one the working folder is in, and whether it has the bridge's
// language to judge, as its config and its files say.
func (b Bridge) here() (string, bool, error) {
	project, named := workspace.JournalProject()
	if !named {
		cwd, _ := os.Getwd()
		project = workspace.ProjectRoot(cwd)
	}

	settings, err := config.Load(project)
	if err != nil {
		return project, false, err
	}

	return project, settings.Holds(project, b.Language), nil
}
