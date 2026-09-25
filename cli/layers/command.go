// Package layers is `layers`: read the dependency stack a project already has and propose its layer
// declaration, or grow a declared stack in place, one layer or one arrow at a time.
package layers

import (
	"os"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine/php/namespaces"
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
		return c.add(in, config.EditorIn(root), console)
	case "allow":
		return c.allow(in, config.EditorIn(root), console)
	default:
		return c.propose(in, root, console)
	}
}

// propose reads the graph of the given path, else of the project's declared roots, and reports or writes
// its shape into this project's config.
func (c Command) propose(in *cli.Input, project string, console cli.Console) (int, error) {
	roots := []string{}

	if given, named := in.FirstArgument(); named {
		roots = append(roots, strings.TrimRight(given, "/"))
	} else if declared, err := config.DeclaredRoots(project); err == nil {
		roots = declared
	} else {
		return 0, err
	}

	codebase, err := scan.Walk(roots, source.Excluded{}).Only(source.PHP).Load()
	if err != nil {
		return 0, err
	}

	graph := namespaces.Of(codebase)
	floorOnly := in.HasFlag("floor")
	proposed := graph.CurrentShape()

	if floorOnly {
		proposed = graph.FloorShape()
	}

	report(graph, proposed, floorOnly, config.EditorIn(project), console)

	if len(proposed) == 0 || !in.HasFlag("write") {
		return 0, nil
	}

	return write(project, layersOf(proposed), in.HasFlag("refresh"), console)
}

func report(graph *namespaces.NamespaceGraph, proposed []namespaces.Layer, floorOnly bool, editor config.Editor, console cli.Console) {
	order := graph.DependencyOrder()
	foundation := graph.FloorShape()

	console.Say("\033[1mNamespace layers\033[0m — " + strconv.Itoa(order.Total()) + " namespaces")
	console.Say("")
	console.Say("  \033[32m" + strconv.Itoa(len(foundation)) + "\033[0m are depended on but depend on nothing of yours — the floor your stack rests on")

	for _, layer := range foundation[:min(12, len(foundation))] {
		console.Say("      " + layer.Namespace)
	}

	if len(foundation) > 12 {
		console.Say("      … and " + strconv.Itoa(len(foundation)-12) + " more")
	}

	if order.HasCycles() {
		pairs := graph.MutualPairs()

		console.Say("")
		console.Say("  \033[33m" + strconv.Itoa(len(order.Cyclic)) + "\033[0m sit in a cycle — declared below as they stand, each permitting the other:")

		for _, pair := range pairs[:min(8, len(pairs))] {
			console.Say("      " + pair[0] + "  ->  " + pair[1])
		}

		console.Say("    \033[2mrun `commandments judge --sin=namespace-cycle` for the exact arrows to cut\033[0m")
	}

	console.Say("")

	if len(proposed) == 0 {
		if floorOnly {
			console.Say("  Nothing at the floor — every namespace here reaches another. Drop --floor for the whole shape.")
		} else {
			console.Say("  Nothing to propose — nothing here references anything else of yours.")
		}

		return
	}

	if floorOnly {
		console.Say("  Proposed declaration — the floor (" + strconv.Itoa(len(proposed)) + " namespaces):")
	} else {
		console.Say("  Proposed declaration — today's shape, held (" + strconv.Itoa(len(proposed)) + " namespaces):")
	}

	hint := ", or --floor for just the bottom"
	if floorOnly {
		hint = ""
	}

	console.Say("")
	console.Say(editor.Declaration(layersOf(proposed)))
	console.Say("")
	console.Say("  \033[2mA starting point to EDIT, not a verdict: everything already here passes, so this" +
		"\n  holds the architecture where it stands and refuses the NEXT arrow somewhere new.\033[0m")
	console.Say("  \033[2mre-run with --write to add it to " + editor.Name() + hint + "\033[0m")
}

func write(project string, layers []config.Layer, refresh bool, console cli.Console) (int, error) {
	editor := config.EditorIn(project)

	if refresh {
		rewritten, err := editor.RewriteLayers(layers)
		if err != nil {
			return 0, err
		}

		if rewritten {
			console.Say("")
			console.Say("\033[32m✓ refreshed the declaration in " + editor.Name() + "\033[0m")
			console.Say("  \033[2mthe stack as it stands today — read the diff before you commit it\033[0m")

			return 0, nil
		}
	}

	written, err := editor.EnsureLayers(layers)
	if err != nil {
		return 0, err
	}

	console.Say("")

	if written {
		console.Say("\033[32m✓ written to " + editor.Name() + "\033[0m")
	} else {
		console.Say("\033[33m• " + strings.TrimPrefix(editor.Name(), ".commandments/") + " already declares layers — left untouched.\033[0m" +
			"\n  \033[2mAdd to it instead: `layers add <Namespace> [--may-use=A,B]`, `layers allow <Layer> <Target>`," +
			"\n  or `layers --write --refresh` to regenerate the whole block from today's shape.\033[0m")
	}

	return 0, nil
}

// layersOf is the shape as the config declares it.
func layersOf(shape []namespaces.Layer) []config.Layer {
	layers := make([]config.Layer, len(shape))

	for i, layer := range shape {
		layers[i] = config.Layer{Namespace: layer.Namespace, MayUse: layer.Uses}
	}

	return layers
}

func (c Command) add(in *cli.Input, file config.Editor, console cli.Console) (int, error) {
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

	for _, use := range normalisedAll(in.List("may-use")) {
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

func (c Command) allow(in *cli.Input, file config.Editor, console cli.Console) (int, error) {
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

func commit(file config.Editor, layers []config.Layer, message string, console cli.Console) (int, error) {
	rewritten, err := file.RewriteLayers(layers)

	if err != nil || !rewritten {
		return fail(file.Name()+" declares no layers yet — run `commandments layers --write` to propose the stack first.", console), err
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

// normalisedAll are the given namespaces normalised, the blank ones dropped.
func normalisedAll(given []string) []string {
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
