package hooks

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
)

const workerCLIKey = "workers_cli"

var cliPrograms = []string{"commandments", "commandments-go", filepath.Base(binary.Release)}

var cliAliases = []string{binary.PluginAlias, binary.Alias, binary.ChangesAlias}

var commandSeparators = strings.NewReplacer("&&", "\n", "||", "\n", ";", "\n", "|", "\n")

func cliRefusedTo(moment Moment) string {
	if moment.Command == nil || !workerRoles[moment.Role] || workersRunTheCLI(moment) || !runsTheCLI(*moment.Command) {
		return ""
	}

	return "Code Commandments — a " + moment.Role + " may not run the commandments CLI or the judge here: the setting " + workerCLIKey +
		" is off. Your work is judged when it is handed in and when it is merged."
}

func workersRunTheCLI(moment Moment) bool {
	said, named := moment.Settings[workerCLIKey]
	if named {
		return switchedOn(said)
	}

	chosen, given := chosenSettings()
	if !given {
		return false
	}

	said, named = chosen[workerCLIKey]

	return named && switchedOn(said)
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

		if slices.Contains(cliPrograms, filepath.Base(words[0])) || slices.Contains(cliAliases, strings.Join(words[:min(2, len(words))], " ")) {
			return true
		}
	}

	return false
}
