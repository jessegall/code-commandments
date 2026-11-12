package hooks

import (
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/binary"
)

const (
	helpersCLIKey   = "workers_cli"
	helpersCLITitle = "Helpers may run the commandments CLI"
)

var cliPrograms = []string{"commandments", "commandments-go", filepath.Base(binary.Release)}

var cliAliases = []string{binary.PluginAlias, binary.Alias, binary.ChangesAlias, "journal rules"}

var commandSeparators = strings.NewReplacer("&&", "\n", "||", "\n", ";", "\n", "|", "\n", "(", "\n", ")", "\n",
	"{", "\n", "}", "\n", "`", "\n", "\"", "\n", "'", "\n")

var commandWrappers = []string{"env", "nice", "nohup", "exec", "command", "time", "timeout", "xargs", "sudo", "caffeinate"}

func cliRefusedTo(moment Moment) string {
	if moment.Command == nil || !workerRoles[moment.Role] || helpersRunTheCLI(moment) || !runsTheCLI(*moment.Command) {
		return ""
	}

	return "Code Commandments — a " + moment.Role + " may not run the commandments CLI or the judge here: the setting \"" +
		helpersCLITitle + "\" (" + helpersCLIKey + ") is off. Your work is judged when it is handed in and when it is merged."
}

func helpersRunTheCLI(moment Moment) bool {
	said, named := moment.Settings[helpersCLIKey]
	if named {
		return switchedOn(said)
	}

	return helpersChosenToRunTheCLI()
}

func helpersChosenToRunTheCLI() bool {
	chosen, given := chosenSettings()
	if !given {
		return false
	}

	said, named := chosen[helpersCLIKey]

	return named && switchedOn(said)
}

func runsTheCLI(command string) bool {
	for _, part := range strings.Split(commandSeparators.Replace(command), "\n") {
		words := strings.Fields(part)

		if slices.Contains(cliAliases, strings.Join(words[:min(2, len(words))], " ")) {
			return true
		}

		for len(words) > 0 && isAssignment(words[0]) {
			if names(strings.SplitN(words[0], "=", 2)[1]) {
				return true
			}

			words = words[1:]
		}

		if len(words) > 0 && (names(programOf(words)) || slices.ContainsFunc(words, isAPathToTheCLI)) {
			return true
		}
	}

	return false
}

func programOf(words []string) string {
	for len(words) > 1 && slices.Contains(commandWrappers, filepath.Base(words[0])) {
		words = words[1:]

		for len(words) > 1 && (strings.HasPrefix(words[0], "-") || isAssignment(words[0]) || isNumber(words[0])) {
			words = words[1:]
		}
	}

	return words[0]
}

func isAPathToTheCLI(word string) bool {
	return strings.Contains(word, "/") && names(word)
}

func names(program string) bool {
	return slices.Contains(cliPrograms, filepath.Base(program))
}

func isAssignment(word string) bool {
	name, _, assigns := strings.Cut(word, "=")

	return assigns && name != "" && !strings.ContainsAny(name, "/-.$")
}

func isNumber(word string) bool {
	return strings.Trim(word, "0123456789.smhd") == ""
}
