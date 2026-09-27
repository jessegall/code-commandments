package info

import (
	"io/fs"
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skills"
)

// width is how wide a paragraph is laid out.
const width = 88

// suggested is how many rules an unknown query is shown instead.
const suggested = 8

// Command is `info`: explain one rule.
type Command struct {
	// Fixable maps a sin to the command that repents it.
	Fixable map[string]string

	// Scaffoldable maps a sin to the command that scaffolds the helper its fix uses.
	Scaffoldable map[string]string
}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"info", "explain"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Explain one sin — what it flags, why it is a sin, how to fix it, and a worked example.").
		Form("info <sin|detector>", "explain a rule by sin id or detector name (lenient match)").
		Form("info --sin=NAME", "the same, named explicitly").
		Form("info --detector=NAME", "the same, by detector class name").
		Option("--sin=NAME", "the sin to explain").
		Option("--detector=NAME", "the detector to explain").
		Option("--full", "print the skill's whole principle, not just the opening").
		Note("The name is matched leniently, so `array-bag`, `ArrayBag` and `ArrayBagDetector` all " +
			"resolve to the same rule. The worked example is read from the published skill, so it is " +
			"the same code the skill teaches. Run `commandments judge --list` for every rule there is.").
		Note("A rule this project wrote into .commandments/custom/ is explained beside the shipped ones " +
			"and named as the project's own — those are the rules a reader is least likely to recognise.")
}

// Run explains the rule the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	query, named := in.Option("sin")

	if !named {
		query, named = in.Option("detector")
	}

	if !named {
		query, named = in.FirstArgument()
	}

	if !named {
		return help.Usage(console.Err, c, "name a sin or detector to explain"), nil
	}

	found := Detectors(query)

	if len(found) == 0 {
		console.Warn("No rule matches \""+query+"\".", "", "Some of the rules there are:")

		for _, name := range Suggestions(suggested) {
			console.Warn("  " + name)
		}

		console.Warn("", "Run `commandments judge --list` for all of them.")

		return 2, nil
	}

	for _, detector := range found {
		c.describe(detector, in.HasFlag("full"), console)
	}

	return 0, nil
}

func (c Command) describe(detector detectors.Detector, full bool, console cli.Console) {
	sin := detector.Sin().Definition()
	teaching := sin.Skill.Definition()

	console.Write("\n\033[1m" + sin.Name + "\033[0m  \033[2m" + sin.Slug() + "\033[0m\n")
	paragraph(console, sin.Description)

	why := teaching.Intro
	if full {
		why += "\n\n" + teaching.Principle
	}

	heading(console, "Why it is a sin")
	paragraph(console, why)
	heading(console, "How to fix it")
	paragraph(console, sin.Rule)

	if sin.Suggestion != "" {
		paragraph(console, sin.Suggestion)
	}

	if example := Example(published(teaching), sin.Name); example != "" {
		heading(console, "Example")

		for _, line := range strings.Split(example, "\n") {
			console.Write("  " + line + "\n")
		}
	}

	heading(console, "Commands")
	row(console, "Learn", "load the skill \033[1m"+teaching.ID()+"\033[0m")

	if _, fixable := c.Fixable[sin.Name]; fixable {
		row(console, "Auto-fix", "commandments repent --sin="+sin.Name)
	}

	if _, scaffolds := c.Scaffoldable[sin.Name]; scaffolds {
		row(console, "Scaffold", "commandments scaffold --sin="+sin.Name)
	}

	if exemptable, honours := detector.(packages.Exemptable); honours && len(exemptable.Exemptions()) > 0 {
		row(console, "Exempts", "commandments exemptions "+sin.Name)
	}

	row(console, "Find it", "commandments judge --sin="+sin.Name)
	row(console, "Turn off", "commandments disable "+sin.Name)
	row(console, "Wrong?", "commandments report --detector="+catalog.Name(detector)+" --reason=\"…\" --ref=PATH:LINE")
	console.Write("\n")
}

// published is the skill's examples, else its SKILL.md: each from the project's published copy first,
// else the copy the binary carries.
func published(teaching skill.Definition) string {
	cwd, _ := os.Getwd()
	library := os.DirFS(workspace.At(consumerRoot(cwd), "").LibraryDir() + "/" + teaching.ID())
	carried, _ := fs.Sub(skills.Files, "commandments/"+teaching.Slug)

	for _, relative := range []string{"reference/examples.md", "SKILL.md"} {
		for _, dir := range []fs.FS{library, carried} {
			if raw, err := fs.ReadFile(dir, relative); err == nil {
				return string(raw)
			}
		}
	}

	return ""
}

// consumerRoot is the nearest folder at or above cwd with a composer.json, stopping at the home folder;
// `.` when there is none.
func consumerRoot(cwd string) string {
	home := resolve(os.Getenv("HOME"))

	for dir := resolve(cwd); dir != "" && dir != filepath.Dir(dir); dir = resolve(filepath.Dir(dir)) {
		if dir == home {
			return "."
		}

		if info, err := os.Stat(dir + "/composer.json"); err == nil && info.Mode().IsRegular() {
			return dir
		}
	}

	return "."
}

// resolve is the path with its links resolved; empty when it does not exist.
func resolve(path string) string {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return ""
	}

	absolute, _ := filepath.Abs(real)

	return absolute
}

func heading(console cli.Console, title string) {
	console.Write("\n\033[33m" + title + "\033[0m\n\n")
}

func paragraph(console cli.Console, text string) {
	wrapped := layout.Wrap(layout.Trim(text), width, "\n")
	console.Write("  " + strings.Join(strings.Split(wrapped, "\n"), "\n  ") + "\n")
}

func row(console cli.Console, label, value string) {
	console.Write("  \033[36m" + layout.PadBytes(label, 10) + "\033[0m " + value + "\n")
}
