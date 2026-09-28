// Package rules is `rule`: the loop of writing a rule of the project's own — see the tree it reads, try it on a
// path, prove it on the samples that mark it, and print the schema an editor checks it by.
package rules

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/rule"
)

// Command is `rule`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"rule"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Work on a rule of your own: see the tree it reads, try it on a path, prove it on the samples that mark it, and print the schema an editor checks it by.").
		Form("rule explain <file> [--line=N]", "print the tree a rule reads in the file: every node's line, kind, neutral kinds, field, name and what it resolves to").
		Form("rule try <Rule> <path>", "run one rule over the path without turning it on, and print each match with its line").
		Form("rule prove [path]", "check every rule of the project flags exactly the code its samples mark, and nothing else").
		Form("rule schema", "print the JSON Schema of a rule file").
		Option("--line=N", "with `explain`, only the nodes that start on line N, each with what it holds").
		Note("<Rule> is a rule of `.commandments/custom/` by its name — `NoRawSql` or `NoRawSqlDetector` — or the path of a rule file anywhere.").
		Note("A sample marks the code a rule must flag with a comment above it — `// @sin NoRawSqlDetector`, `# @sin …` in Python, `<!-- @sin … -->` in a Vue template — the code it must leave alone with `@righteous NoRawSqlDetector`, and its fix with `@fixed NoRawSqlDetector`. `rule prove` reads `.commandments/custom/samples/` unless given a path, and fails when a rule misses a mark, flags anything unmarked, has no `@sin` mark at all, or cannot be read, and when a mark names no rule of the project.")
}

// Run answers the form the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	root, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	switch verb, _ := in.FirstArgument(); verb {
	case "explain":
		return c.explain(in, console)
	case "try":
		return c.try(in, root, console)
	case "prove":
		return c.prove(in, root, console)
	case "schema":
		console.Write(string(rule.Schema()))

		return 0, nil
	default:
		return help.Usage(console.Err, c, "name what to do: `rule explain`, `rule try`, `rule prove` or `rule schema`."), nil
	}
}

// explain prints the tree of the file, or of the nodes starting on one line of it.
func (c Command) explain(in *cli.Input, console cli.Console) (int, error) {
	path, named := in.Argument(1)
	if !named {
		return help.Usage(console.Err, c, "name the file to explain, e.g. `commandments rule explain app/Http/OrderController.php`."), nil
	}

	line := 0
	if given, set := in.Option("line"); set {
		parsed, err := strconv.Atoi(given)
		if err != nil || parsed < 1 {
			return help.Usage(console.Err, c, "--line="+given+" is no line number."), nil
		}

		line = parsed
	}

	if info, err := os.Stat(path); err != nil || !info.Mode().IsRegular() || !source.Judges(path) {
		return console.Refuse(path + " is no file the tool reads — name one source file: PHP, Vue, TypeScript, Python or C#."), nil
	}

	codebase, err := scan.OneFile(path).Load()
	if err != nil {
		return 0, err
	}

	if len(codebase.Files()) == 0 {
		return console.Refuse(path + " holds nothing the tool could read."), nil
	}

	root := codebase.Files()[0].Match(0)
	shown := Tree(root, line)
	if shown == "" {
		return console.Refuse(fmt.Sprintf("No node starts on line %d of %s.", line, path)), nil
	}

	console.Write(shown)

	return 0, nil
}

// try runs one rule over a path, printing each match and how many there are.
func (c Command) try(in *cli.Input, root string, console cli.Console) (int, error) {
	named, given := in.Argument(1)
	path, pathed := in.Argument(2)
	if !given || !pathed {
		return help.Usage(console.Err, c, "name the rule and the path to try it on, e.g. `commandments rule try NoRawSql app/`."), nil
	}

	project, err := config.Load(root)
	if err != nil {
		return 0, err
	}

	own := custom.Load(root)

	tried, err := ruleNamed(own, named)
	if err != nil {
		return console.Refuse(err.Error()), nil
	}

	tried = tried.WithLayers(project.Layers(tried.Engine()))

	codebase, err := scan.Walk([]string{path}, source.Under(root, project.Excluded)).Only(source.OfEngine(tried.Engine())...).Load()
	if err != nil {
		return 0, err
	}

	found := tried.Find(codebase)
	for _, match := range found {
		console.Write(fmt.Sprintf("%s:%d  %s\n", relative(root, match.File()), match.Line(), sourceLine(match)))
	}

	console.Write(fmt.Sprintf("%d match(es) of %s in %s\n", len(found), tried.Name(), path))

	return 0, nil
}

// prove checks every rule of the project against the markers of its samples: each flags exactly the code marked
// @sin for it, and has such a mark; and every mark names a rule of the project.
func (c Command) prove(in *cli.Input, root string, console cli.Console) (int, error) {
	path := workspace.SamplesDir(root)
	if given, named := in.Argument(1); named {
		path = given
	}

	own := custom.Load(root)
	for _, reason := range own.Unreadable {
		console.Warn("\033[31m✗\033[0m .commandments/custom/" + reason)
	}

	if len(own.Rules) == 0 {
		return console.Refuse("No rules in .commandments/custom/ to prove — scaffold one with `commandments make <Name>`."), nil
	}

	if _, err := os.Stat(path); err != nil {
		return console.Refuse("No samples at " + path + " — write files there that mark what each rule must flag with `@sin <Rule>`."), nil
	}

	project, err := config.Load(root)
	if err != nil {
		return 0, err
	}

	var proven []detectors.Detector
	var languages []source.Language
	for _, each := range own.Rules {
		proven = append(proven, each.WithLayers(project.Layers(each.Engine())))
		languages = append(languages, source.OfEngine(each.Engine())...)
	}

	codebase, err := scan.Walk([]string{path}, source.Excluded{}).Only(languages...).Load()
	if err != nil {
		return 0, err
	}

	markers := fixture.Markers(codebase)
	failed := len(own.Unreadable) > 0

	for at, result := range (fixture.Fixture{Codebase: codebase, Detectors: proven}).Verify() {
		unmarked := len(fixture.Marking(markers, fixture.Sinful, proven[at])) == 0
		if result.Passed() && !unmarked {
			console.Write("\033[32m✓\033[0m " + result.Detector + "\n")
			warnUntwinned(markers, proven[at], console)

			continue
		}

		failed = true
		console.Write("\033[31m✗\033[0m " + result.Detector + "\n")

		if unmarked {
			console.Write("    no sample marks what it must flag — mark one with `@sin " + result.Detector + "`\n")
		}

		for _, reason := range []struct {
			says   string
			places []string
		}{
			{"missed the mark at", result.Missed},
			{"flagged unmarked code at", result.Unexpected},
			{"flagged its righteous twin at", result.FlaggedRighteous},
			{"flagged its fix at", result.FlaggedFixed},
		} {
			for _, place := range reason.places {
				console.Write("    " + reason.says + " " + relative(root, place) + "\n")
			}
		}
	}

	for _, marker := range markers {
		if !slices.ContainsFunc(proven, marker.Naming) {
			failed = true
			console.Write("\033[31m✗\033[0m " + relative(root, marker.Location) + " marks " + marker.Name + ", which is no rule of the project\n")
		}
	}

	if failed {
		return cli.Refused, nil
	}

	return 0, nil
}

// warnUntwinned says when no sample marks a look-alike the rule must leave alone: a rule proven only on what it
// flags has not been shown to leave anything be.
func warnUntwinned(markers []fixture.Marker, rule detectors.Detector, console cli.Console) {
	if len(fixture.Marking(markers, fixture.Righteous, rule)) == 0 {
		console.Write("    \033[33m⚠\033[0m no `@righteous` look-alike shows it leaves anything alone\n")
	}
}

// ruleNamed is the rule a name means: a file when it names one, else a rule of the project's own.
func ruleNamed(own custom.Project, named string) (rule.Rule, error) {
	if strings.HasSuffix(named, ".json") {
		return own.Read(named)
	}

	if found, known := own.Named(named); known {
		return found, nil
	}

	var names []string
	for _, each := range own.Rules {
		names = append(names, each.Name())
	}

	unreadable := ""
	if len(own.Unreadable) > 0 {
		unreadable = "\nand cannot read:\n  " + strings.Join(own.Unreadable, "\n  ")
	}

	has := "none"
	if len(names) > 0 {
		has = strings.Join(names, ", ")
	}

	return rule.Rule{}, fmt.Errorf("no rule %q in .commandments/custom/ — the project has %s%s", named, has, unreadable)
}

// Tree is the node and everything below it, a line each, indented by depth; with a line, only the nodes starting
// on it, each with what it holds.
func Tree(root engine.Match, line int) string {
	var tree strings.Builder

	var walk func(node engine.Match, depth int, shown bool)
	walk = func(node engine.Match, depth int, shown bool) {
		shown = shown || line == 0 || node.Line() == line
		if shown {
			tree.WriteString(Describe(node, depth))
		}

		for _, child := range node.Children() {
			walk(child, depth+1, shown)
		}
	}

	walk(root, 0, false)

	return tree.String()
}

// Describe is one node as explain prints it: its line, then, indented, its kind, the neutral kinds it answers,
// the field it fills, its name, its text and what it resolves to.
func Describe(node engine.Match, depth int) string {
	parts := []string{node.Kind()}

	var neutral []string
	for _, kind := range engine.Neutrals {
		if node.Is(kind) {
			neutral = append(neutral, string(kind))
		}
	}

	if len(neutral) > 0 {
		parts = append(parts, "["+strings.Join(neutral, ", ")+"]")
	}

	if field := node.Node().Field; field != "" {
		parts = append(parts, "field="+field)
	}

	if name := node.Name(); name != "" {
		parts = append(parts, fmt.Sprintf("name=%q", name))
	}

	if text, isText := node.Text(); isText {
		parts = append(parts, fmt.Sprintf("text=%q", text))
	}

	if refers := node.Refers(); refers != "" {
		parts = append(parts, "resolves="+refers)
	}

	return fmt.Sprintf("%5d  %s%s\n", node.Line(), strings.Repeat("  ", depth), strings.Join(parts, "  "))
}

// sourceLine is the line the match starts on, as its file spells it, trimmed.
func sourceLine(match engine.Match) string {
	source, err := match.Source().Source()
	if err != nil {
		return ""
	}

	lines := strings.Split(string(source), "\n")
	if match.Line() < 1 || match.Line() > len(lines) {
		return ""
	}

	return strings.TrimSpace(lines[match.Line()-1])
}

// relative is the path from the project root, when it lies inside it.
func relative(root, path string) string {
	if inside, err := filepath.Rel(root, path); err == nil && !strings.HasPrefix(inside, "..") {
		return inside
	}

	return path
}
