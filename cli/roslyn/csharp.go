package roslyn

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// HasCSharp is `has-csharp`.
type HasCSharp struct{}

// Names are the verbs it answers to.
func (HasCSharp) Names() []string {
	return []string{"has-csharp"}
}

// Help documents it.
func (HasCSharp) Help() help.Help {
	return help.Of("Say whether this project has C# to judge: exit 0 when it has, 1 when it has none.").
		Form("has-csharp", "answer for the project the journal runs it for, else the one the working folder is in").
		Note("The journal asks it before it starts the plugin's roslyn service, so a project with no C# keeps no C# bridge and lists none as running.").
		In(help.Hooks)
}

// Run answers for the project the journal runs it for, else the one the working folder is in.
func (HasCSharp) Run(_ *cli.Input, console cli.Console) (int, error) {
	project, holds, err := csharpHere()
	if err != nil {
		return 0, err
	}

	if !holds {
		return console.Refuse(project + " has no C# to judge."), nil
	}

	return console.Say(project + " has C# to judge."), nil
}

// csharpHere is the project the journal runs it for, else the one the working folder is in, and whether it has C#
// to judge, as its config and its files say.
func csharpHere() (string, bool, error) {
	project, named := workspace.JournalProject()
	if !named {
		cwd, _ := os.Getwd()
		project = workspace.ProjectRoot(cwd)
	}

	settings, err := config.Load(project)
	if err != nil {
		return project, false, err
	}

	return project, settings.Holds(project, source.CSharp), nil
}
