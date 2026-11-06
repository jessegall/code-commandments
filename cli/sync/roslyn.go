package sync

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
)

func hostRoslynBuilds() string {
	home, _ := os.UserHomeDir()

	return filepath.Join(home, ".cache", "code-commandments", "roslyn-bridge")
}

func removeHostRoslynBuilds(console cli.Console) {
	builds := hostRoslynBuilds()
	if _, err := os.Stat(builds); err != nil {
		return
	}
	if err := os.RemoveAll(builds); err != nil {
		console.Warn("could not remove the C# bridge the PHP tool built on this machine, " + builds + ": " + err.Error())

		return
	}
	console.Say("↻ removed the C# bridge the PHP tool built on this machine (" + builds + "): C# is read by the bridge each release ships now.")
}

func fetchRoslyn(root string, project config.Config, console cli.Console) {
	if !project.Holds(root, source.CSharp) || os.Getenv("COMMANDMENTS_ROSLYN") != "" {
		return
	}
	executable, fetched, err := bridge.RoslynExecutable()
	if err != nil {
		console.Warn(err.Error())

		return
	}
	if fetched {
		console.Say("↓ fetched the C# bridge for " + bridge.Release + " (" + executable + ").")
	}
	if notice := bridge.RoslynNotice(); notice != "" {
		console.Warn(notice)
	}
}
