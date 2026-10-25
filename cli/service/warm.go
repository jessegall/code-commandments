package service

import (
	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

// warm asks the bridge for the project's own files as soon as the service is listening, so the first run of the
// tool finds a bridge that has already read the project instead of paying for it.
//
// The journal starts a plugin's services when it is installed and at every session start, so a bridge has seconds
// of idle life before anything asks it anything; this is that time spent. It runs BEHIND the listener, never in
// front of it: an ask arriving while it reads is answered, waiting only on the bridge's own lock, which is the
// wait that ask would have paid in any case. A warm-up that fails says so and the service serves cold, since a
// bridge that cannot read the project now may still answer the one file a hook asks about.
func warm(held Bridge, project string, server *bridge.Server, console cli.Console) {
	files := filesOf(held, project)
	if len(files) == 0 {
		return
	}

	if _, err := server.AskTallied(held.Request([]string{project}, files), func() {}); err != nil {
		console.Warn("The " + held.Label + " bridge could not be read into warm, so the first run pays for it: " + err.Error())

		return
	}

	console.Say("The " + held.Label + " bridge is warm: " + natural.Counted(len(files), "file") + " of " + project + " read.")
}

// filesOf are the project's own files the bridge reads: exactly what a judge of this project walks, the folders it
// declares less the ones it leaves out, so warming loads what will be asked for and nothing else.
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
