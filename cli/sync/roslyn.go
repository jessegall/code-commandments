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
	console.Say("↻ removed the C# bridge the PHP tool built on this machine (" + builds + "): C# is read by the bridge each release ships now.")
}

// fetchRoslyn fetches this release's C# bridge for a project that writes C#, when the cache does not hold it yet, so
// the first judge does not wait on it. It is never built here; a fetch that fails leaves C# unjudged, and says why.
func fetchRoslyn(root string, project config.Config, console cli.Console) {
	if !project.Writes(source.CSharp) || len(source.FilesIn(root, "cs", source.Under(root, project.Excluded))) == 0 || os.Getenv("COMMANDMENTS_ROSLYN") != "" {
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
