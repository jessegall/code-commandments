package dashboard

import (
	"os"
	"os/exec"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const refreshVerb = "dashboard-refresh"

func Schedule(space workspace.Workspace) error {
	self, err := os.Executable()
	if err != nil {
		return err
	}
	run := exec.Command(self, refreshVerb, "--queue")
	run.Dir = workspace.SettingsRoot(space.Root())
	detach(run)
	if err := run.Start(); err != nil {
		return err
	}
	go run.Wait()
	return nil
}

// RefreshCommand renders the dashboard from the findings on disk.
type RefreshCommand struct{}

func (RefreshCommand) Names() []string { return []string{refreshVerb} }

func (RefreshCommand) Help() help.Help {
	return help.Of("Refresh the journal dashboard from saved findings.").
		Form(refreshVerb).
		Option("--queue", "submit a background refresh through the journal when available").
		In(help.Hooks)
}

func (RefreshCommand) Run(in *cli.Input, _ cli.Console) (int, error) {
	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}
	space := workspace.At(root, "")
	if in.HasFlag("queue") && enqueue(space) {
		return 0, nil
	}
	return 0, refresh(space)
}

func enqueue(space workspace.Workspace) bool {
	journal := space.Journal()
	self, err := os.Executable()
	if journal == "" || err != nil {
		return false
	}
	run := exec.Command(journal, "plugin", "queue", workspace.JournalPlugin, "--kind", refreshVerb, "--checkout", space.Root(),
		"--priority", "background", "--detach", "--", self, refreshVerb)
	run.Dir = space.Root()
	return run.Run() == nil
}

func refresh(space workspace.Workspace) error {
	release := atomic.Hold(space.Cache(File + ".lock"))
	defer release()

	data, err := jsonfile.Pretty(RenderWith(storedIn(space), space.Root(), Unjudgeable(space)), true)
	if err != nil {
		return err
	}
	return atomic.Write(space.Cache(File), data)
}
