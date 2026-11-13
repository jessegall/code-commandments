package sync

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/walk"
)

func hostRoslynBuilds() string {
	ours, _ := cache.Ours()

	return filepath.Join(ours, "roslyn-bridge")
}

func removeHostRoslynBuilds(console cli.Console) {
	builds := hostRoslynBuilds()
	if !walk.Exists(builds) {
		return
	}
	if err := os.RemoveAll(builds); err != nil {
		console.Warn("could not remove the C# bridge the PHP tool built on this machine, " + builds + ": " + err.Error())

		return
	}
	console.Say("↻ removed the C# bridge the PHP tool built on this machine (" + builds + "): C# is read by the bridge each release ships now.")
}

// prepareBridges readies every bridge the project holds a language of, and passes on what each says.
func prepareBridges(root string, project config.Config, console cli.Console) {
	for _, held := range scan.Bridges {
		if held.Prepare == nil || !held.HeldIn(func(language source.Language) bool { return project.Holds(root, language) }) {
			continue
		}
		said, err := held.Prepare()
		if err != nil {
			console.Warn(err.Error())

			continue
		}
		if said != "" {
			console.Say(said)
		}
		if notice := held.Notice(); notice != "" {
			console.Warn(notice)
		}
	}
}
