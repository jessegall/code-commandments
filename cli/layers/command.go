// Package layers is `layers`: read the dependency stack a project already has and propose its layer
// declaration, or grow a declared stack in place, one layer or one arrow at a time.
package layers

import (
	"errors"
	"os"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
)

// Command is `layers`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"layers"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Read the dependency stack this project ALREADY has and propose the layer declaration for it — the rule is inert until one is declared, and nobody writes that from a blank file.").
		Form("layers [path]", "propose today's shape: every namespace with what it already uses").
		Form("layers [path] --floor", "propose only the bottom — what others depend on and that depends on nothing").
		Form("layers [path] --write", "add the proposal to .commandments/config.php").
		Form("layers add <Namespace> [--may-use=A,B]", "declare a new layer, or widen a declared one, in place").
		Form("layers allow <Layer> <Target>", "one more arrow, in place").
		Option("--floor", "propose only the bottom layer").
		Option("--write", "write the proposal into .commandments/config.php").
		Option("--refresh", "with --write, regenerate a block that is already declared").
		Option("--may-use=A,B", "with `add`, the namespaces the new layer may depend on").
		Note("Once a stack is declared the proposal refuses to overwrite it — a GROWING codebase edits it " +
			"INCREMENTALLY with `add`/`allow`, or regenerates the whole block from today's shape with `--write --refresh`. Every edit goes through the AST and replaces only the ->layer(...) chain, keeping your config's own formatting.")
}

// Run answers the form the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	switch verb, _ := in.FirstArgument(); verb {
	case "add":
		return c.add(in, config.FileIn(root), console)
	case "allow":
		return c.allow(in, config.FileIn(root), console)
	default:
		return 0, errors.New("proposing a layer stack reads the PHP engine's namespace graph, which this build does not have yet")
	}
}

func (c Command) add(in *cli.Input, file config.File, console cli.Console) (int, error) {
	named, given := in.Argument(1)
	if !given {
		return c.usage("layers add <Namespace> [--may-use=A,B]", console), nil
	}

	layers, err := file.Layers()
	if err != nil {
		return 0, err
	}

	namespace := normalise(named)
	at := indexOf(layers, namespace)
	var existing []string

	if at >= 0 {
		existing = layers[at].MayUse
	}

	var added []string

	for _, use := range namespaces(in.List("may-use")) {
		if !slices.Contains(existing, use) {
			added = append(added, use)
		}
	}

	if at < 0 {
		layers = append(layers, config.Layer{Namespace: namespace, MayUse: added})
	} else {
		layers[at].MayUse = append(slices.Clone(existing), added...)
	}

	return commit(file, layers, addReport(namespace, added, at >= 0), console)
}

func addReport(namespace string, added []string, declared bool) string {
	switch {
	case !declared && len(added) == 0:
		return "✓ declared " + namespace + " (reaching nothing yet)"
	case !declared:
		return "✓ declared " + namespace + " → " + strings.Join(added, ", ")
	case len(added) == 0:
		return "• " + namespace + " already reaches those layers — nothing to add"
	default:
		return "✓ " + namespace + " may now use " + strings.Join(added, ", ")
	}
}

func (c Command) allow(in *cli.Input, file config.File, console cli.Console) (int, error) {
	arguments := in.Arguments()
	if len(arguments) < 3 {
		return c.usage("layers allow <Layer> <Target>", console), nil
	}

	from, to := normalise(arguments[1]), normalise(arguments[2])

	layers, err := file.Layers()
	if err != nil {
		return 0, err
	}

	at := indexOf(layers, from)

	if at < 0 {
		return fail(from+" is not a declared layer — run `commandments layers add "+from+"` first, or check the spelling.", console), nil
	}

	if slices.Contains(layers[at].MayUse, to) {
		return done("• "+from+" may already use "+to, console), nil
	}

	layers[at].MayUse = append(slices.Clone(layers[at].MayUse), to)

	return commit(file, layers, "✓ "+from+" may now use "+to, console)
}

func commit(file config.File, layers []config.Layer, message string, console cli.Console) (int, error) {
	rewritten, err := file.RewriteLayers(layers)

	if err != nil || !rewritten {
		return fail(".commandments/config.php declares no layers yet — run `commandments layers --write` to propose the stack first.", console), err
	}

	return done(message, console), nil
}

func indexOf(layers []config.Layer, namespace string) int {
	for i, layer := range layers {
		if layer.Namespace == namespace {
			return i
		}
	}

	return -1
}

// namespaces are the given namespaces normalised, the blank ones dropped.
func namespaces(given []string) []string {
	var kept []string

	for _, namespace := range given {
		if normalised := normalise(namespace); normalised != "" {
			kept = append(kept, normalised)
		}
	}

	return kept
}

// normalise is a namespace with doubled separators single and no leading one.
func normalise(namespace string) string {
	return strings.TrimLeft(strings.ReplaceAll(strings.Trim(namespace, " \t\n\r\x00\x0B"), `\\`, `\`), `\`)
}

func (c Command) usage(form string, console cli.Console) int {
	return help.Usage(console.Err, c, "Incomplete — that form reads `commandments "+form+"`.")
}

func fail(message string, console cli.Console) int {
	console.Warn("\033[31m✗ " + message + "\033[0m")

	return 2
}

func done(message string, console cli.Console) int {
	return console.Say("\033[32m" + message + "\033[0m")
}
