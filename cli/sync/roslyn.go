package sync

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
)

// hostRoslynBuilds is where the PHP tool built the C# bridge on the host, before it ran only in its image.
func hostRoslynBuilds() string {
	home, _ := os.UserHomeDir()

	return filepath.Join(home, ".cache", "code-commandments", "roslyn-bridge")
}

// removeHostRoslynBuilds deletes the C# bridge builds the PHP tool left on the host: .NET no longer runs here, and the
// builds only take room.
func removeHostRoslynBuilds(console cli.Console) {
	builds := hostRoslynBuilds()
	if _, err := os.Stat(builds); err != nil {
		return
	}
	if err := os.RemoveAll(builds); err != nil {
		console.Warn("could not remove the C# bridge the PHP tool built on this machine, " + builds + ": " + err.Error())

		return
	}
	console.Say("↻ removed the C# bridge the PHP tool built on this machine (" + builds + "): C# is read only in its image now.")
}

// pullRoslyn pulls the C# bridge's image for a project that writes C#, when docker does not hold it yet. It is never
// built here; a pull that fails leaves C# unjudged, and says which image to pull.
func pullRoslyn(root string, project config.Config, console cli.Console) {
	if !project.Writes(source.CSharp) || len(source.FilesIn(root, "cs", source.Under(root, project.Excluded))) == 0 || bridge.RoslynInstalled() {
		return
	}
	if err := bridge.PullRoslyn(); err != nil {
		console.Warn(bridge.RoslynMissing())

		return
	}
	console.Say("↓ pulled the C# bridge image " + bridge.RoslynImage() + ".")
}
