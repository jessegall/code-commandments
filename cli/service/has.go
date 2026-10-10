package service

import (
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

// Has is `has-csharp`, `has-python`: whether the project has the bridge's language to judge.
type Has struct {
	Bridge
}

// Names are the verbs it answers to.
func (h Has) Names() []string {
	return []string{"has-" + h.Word}
}

// Help documents it.
func (h Has) Help() help.Help {
	return help.Of("Say whether this project has "+h.Label+" to judge: exit 0 when it has, 1 when it has none.").
		Form("has-"+h.Word, "answer for the project the journal runs it for, else the one the working folder is in").
		Note("The journal asks it before it starts the plugin's " + h.Kind + " service, so a project with no " + h.Label + " keeps no " + h.Label + " bridge and lists none as running.").
		In(help.Hooks)
}

// Run answers for the project the journal runs it for, else the one the working folder is in.
func (h Has) Run(_ *cli.Input, console cli.Console) (int, error) {
	project, holds, err := h.here()
	if err != nil {
		return 0, err
	}

	if !holds {
		return console.Refuse(project + " has no " + h.Label + " to judge."), nil
	}

	return console.Say(project + " has " + h.Label + " to judge."), nil
}
