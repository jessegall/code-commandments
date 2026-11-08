package hooks

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
)

const workerCLIKey = "workers_cli"

var cliPrograms = []string{"commandments", "commandments-go", filepath.Base(binary.Release)}

var commandSeparators = strings.NewReplacer("&&", "\n", "||", "\n", ";", "\n", "|", "\n")

func cliRefusedTo(moment Moment) string {
	if moment.Command == nil || !workerRoles[moment.Role] || workersRunTheCLI(moment) || !runsTheCLI(*moment.Command) {
		return ""
	}

	return "Code Commandments — a " + moment.Role + " may not run the commandments CLI here: the setting " + workerCLIKey +
		" is off. Use `" + binary.Alias + " --changes` to judge your own work."
}

func workersRunTheCLI(moment Moment) bool {
	said, named := moment.Settings[workerCLIKey]
	if named {
		return switchedOn(said)
	}

	chosen, given := chosenSettings()
	if !given {
		return true
	}

	said, named = chosen[workerCLIKey]

	return !named || switchedOn(said)
}

func runsTheCLI(command string) bool {
	for _, part := range strings.Split(commandSeparators.Replace(command), "\n") {
		words := strings.Fields(part)

		for len(words) > 0 && strings.Contains(words[0], "=") {
			words = words[1:]
		}

		if len(words) == 0 {
			continue
		}

		if slices.Contains(cliPrograms, filepath.Base(words[0])) || strings.Join(words[:min(2, len(words))], " ") == binary.PluginAlias {
			return true
		}
	}

	return false
}
