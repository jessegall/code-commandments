package hooks

import (
	"os"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Dispatch is `hooks`: the one entry point every wired hook runs.
type Dispatch struct{}

// Names are the verbs it answers to.
func (Dispatch) Names() []string {
	return []string{"hooks"}
}

// Help documents it.
func (Dispatch) Help() help.Help {
	return help.Of("The wired hook entry point — reads one hook payload from stdin, runs every registered handler, and merges their responses into one.").
		Form("hooks", "dispatch the moment on stdin (wired by `install`/`sync`; you rarely run this by hand)").
		In(help.Hooks)
}

// Run answers the moment on stdin with every hook the project keeps, merged.
func (Dispatch) Run(in *cli.Input, console cli.Console) (int, error) {
	event := eventOnStdin()

	var responses []Response
	for _, hook := range ForProject(event.Root) {
		responses = append(responses, Answer(hook, event))
	}

	if merged := Merge(responses); !merged.IsSilent() {
		console.Write(merged.JSON(event.Name()))
	}

	return 0, nil
}

// Runner is `hook <Class>`: one hook run on its own.
type Runner struct{}

// Names are the verbs it answers to.
func (Runner) Names() []string {
	return []string{"hook"}
}

// Help documents it.
func (Runner) Help() help.Help {
	return help.Of("Run ONE hook class directly — the form every wired hook is written as, built-in or a consumer's own $config->hook(...).").
		Form("hook <Class>", "instantiate that Hook and run it against the payload on stdin").
		In(help.Hooks)
}

// Run answers the moment on stdin with the hook the class names.
func (r Runner) Run(in *cli.Input, console cli.Console) (int, error) {
	class, _ := in.FirstArgument()

	hook, found := Named(class)
	if !found {
		return help.Usage(console.Err, r, "'"+trimSlash(class)+`' is not a runnable JesseGall\CodeCommandments\Hooks\Hook.`), nil
	}

	return respond(hook, console), nil
}

// Single is one hook answering to a verb of its own, as `judge-reminder` does, described by About.
type Single struct {
	Verbs []string
	Hook  Hook
	About string
}

// Names are the verbs it answers to.
func (s Single) Names() []string {
	return s.Verbs
}

// Help documents it.
func (s Single) Help() help.Help {
	return help.Of(s.About).
		Form(s.Verbs[0], "run this hook — it reads its payload from stdin, not from flags").
		In(help.Hooks)
}

// Run answers the moment on stdin with the hook.
func (s Single) Run(in *cli.Input, console cli.Console) (int, error) {
	return respond(s.Hook, console), nil
}

func respond(hook Hook, console cli.Console) int {
	event := eventOnStdin()

	if response := Answer(hook, event); !response.IsSilent() {
		console.Write(response.JSON(event.Name()))
	}

	return 0
}

// eventOnStdin is the moment the harness wrote on stdin, in the project the command runs for.
func eventOnStdin() Event {
	cwd, _ := os.Getwd()

	return NewEvent(ReadPayload(os.Stdin), workspace.ProjectRoot(cwd))
}

func trimSlash(class string) string {
	for len(class) > 0 && class[0] == '\\' {
		class = class[1:]
	}

	return class
}
