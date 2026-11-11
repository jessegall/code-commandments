package cli

import (
	"errors"
	"fmt"
	"slices"

	"github.com/jessegall/code-commandments/cli/help"
)

const defaultVerb = "judge"

// Kernel is the one entry point behind the binary: it parses the arguments once and dispatches to the Command registered for the verb.
type Kernel struct {
	commands []Command
	registry map[string]Command
	version  string
}

func NewKernel(version string, commands ...Command) *Kernel {
	kernel := &Kernel{commands: commands, registry: map[string]Command{}, version: version}

	for _, command := range commands {
		for _, name := range command.Names() {
			kernel.registry[name] = command
		}
	}

	return kernel
}

func (k *Kernel) Run(args []string, console Console) int {
	in := FromArgs(args)
	named := in.Command()

	if slices.Contains([]string{"-h", "--help", "help"}, named) {
		verb, _ := in.FirstArgument()

		return k.help(verb, console)
	}

	if in.WantsHelp() {
		return k.help(named, console)
	}

	if named == "" && in.HasFlag("version") {
		return console.Say("code-commandments " + k.version)
	}

	verb := named
	if verb == "" {
		verb = defaultVerb
	}

	handler, known := k.registry[verb]

	if !known {
		console.Warn(fmt.Sprintf("✗ Unknown command '%s'. Try: commandments --help", verb))

		return 2
	}

	if unknown := undeclared(in, handler); unknown != "" {
		return k.refuseOption(unknown, named, console)
	}

	code, err := handler.Run(in, console)

	var invalid *InvalidConfiguration

	if errors.As(err, &invalid) {
		console.Warn("✗ .commandments/config.php: " + invalid.Reason)

		return 2
	}

	if err != nil {
		console.Warn("✗ " + err.Error())

		return 1
	}

	return code
}

func (k *Kernel) refuseOption(unknown, named string, console Console) int {
	if named == "" {
		console.Warn(fmt.Sprintf("✗ Unknown option --%s. Try: commandments --help", unknown))

		return 2
	}

	console.Warn(fmt.Sprintf("✗ Unknown option --%s for `%s`. Try: commandments %s --help", unknown, named, named))

	return 2
}

func undeclared(in *Input, command Command) string {
	declared := command.Help().OptionNames()

	for _, given := range in.Given() {
		if !slices.Contains(declared, given) {
			return given
		}
	}

	return ""
}

func (k *Kernel) help(verb string, console Console) int {
	screen := help.NewScreen(k.documented())

	if command, known := k.registry[verb]; known && verb != "" {
		console.Write(screen.Page(command))

		return 0
	}

	console.Write(screen.Overview())

	return 0
}

func (k *Kernel) Commands() []Command {
	return k.commands
}

func (k *Kernel) documented() []help.Documented {
	documented := make([]help.Documented, len(k.commands))

	for i, command := range k.commands {
		documented[i] = command
	}

	return documented
}
