package config

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/skill"
)

// Command is `config`: what the project configures, what actually runs, and re-detecting its roots.
type Command struct {
	// Version is the tool's own version, shown at the top of the overview.
	Version string
}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"config"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Inspect and manage .commandments/config.php — what is configured, and what is actually running.").
		Form("config", "the effective configuration: source roots, detectors running vs available, packages, skills").
		Form("config reindex", "re-detect the source roots from composer.json and rewrite the config's paths()")
}

// Run answers the form the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	switch verb, _ := in.FirstArgument(); verb {
	case "", "about":
		return c.about(root, console)
	case "reindex":
		return reindex(root, console)
	default:
		return help.Usage(console.Err, c, "Unknown subcommand '"+verb+"'."), nil
	}
}

func (c Command) about(root string, console cli.Console) (int, error) {
	project, err := Load(root)
	if err != nil {
		return 0, err
	}

	enabled, err := project.Enabled(InstalledIn(root))
	if err != nil {
		return 0, err
	}

	roots := project.Paths
	if len(roots) == 0 {
		roots = DetectRoots(root)
	}

	file := EditorIn(root).Name()
	if _, err := os.Stat(filepath.Join(root, file)); err != nil {
		file += " (not yet written)"
	}

	console.Write("\n  \033[1mcode-commandments\033[0m  " + c.Version + "\n\n")
	row(console, "Config", file)
	row(console, "Source roots", strings.Join(roots, ", "))

	for _, engine := range []struct {
		label   string
		engines []catalog.Engine
	}{
		{"Backend detectors", []catalog.Engine{catalog.Backend}},
		{"Frontend detectors", []catalog.Engine{catalog.Frontend, catalog.TypeScript}},
		{"Python detectors", []catalog.Engine{catalog.Python}},
		{"C# detectors", []catalog.Engine{catalog.CSharp}},
	} {
		row(console, engine.label, strconv.Itoa(countIn(enabled, engine.engines))+" running  ·  "+strconv.Itoa(countIn(detectors.All(), engine.engines))+" available")
	}

	row(console, "Custom detectors", strconv.Itoa(len(project.Detectors)))
	row(console, "Exemption packages", strconv.Itoa(len(packages.Shipped))+" built-in  ·  "+strconv.Itoa(len(project.Packages))+" registered")
	row(console, "Skills", strconv.Itoa(len(skill.All())))
	console.Write("\n  \033[2mRun `commandments config reindex` to re-detect the source roots.\033[0m\n\n")

	return 0, nil
}

func reindex(root string, console cli.Console) (int, error) {
	roots := DetectRoots(root)

	editor := EditorIn(root)

	if err := editor.RewritePaths(roots); err != nil {
		return 0, err
	}

	return console.Say("\033[32m✓ Reindexed " + strconv.Itoa(len(roots)) + " source root(s) into " + editor.Name() + ":\033[0m " + strings.Join(roots, ", ")), nil
}

func countIn(list []detectors.Detector, engines []catalog.Engine) int {
	count := 0

	for _, detector := range list {
		engine, _ := detectors.EngineOf(detector)

		for _, wanted := range engines {
			if engine == wanted {
				count++
			}
		}
	}

	return count
}

// row is one `label ....... value` line.
func row(console cli.Console, label, value string) {
	console.Write("  \033[36m" + label + " " + strings.Repeat(".", max(0, 22-len(label)-1)) + "\033[0m " + value + "\n")
}
