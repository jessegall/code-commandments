// Package rules is `rule`: the loop of writing a rule of the project's own — see the tree it reads, try it on a path, prove it on the samples that mark it, and print the schema an editor checks it by.
package rules

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/rules"
	"github.com/jessegall/code-commandments/walk"
)

// Command is `rule`.
type Command struct{}

func (Command) Names() []string {
	return []string{"rule"}
}

func (Command) Help() help.Help {
	return help.Of("Work on a rule of your own: see the tree it reads, try it on a path, prove it on the samples that mark it, and print the schema an editor checks it by.").
		Form("rule explain <file> [--line=N]", "print the generic tree a rule reads in the file: every node's line, the role it holds in its parent, its kind, form, name, text and what it resolves to").
		Form("rule try <Rule> <path>…", "run one rule over the paths without turning it on, and print each match with its line: a folder, or the files a change touched").
		Form("rule prove [path]", "check every rule of the project flags exactly the code its samples mark, and nothing else").
		Form("rule prove --all [path]", "check every shipped rule and every rule of the project against each fixture project under the path (tests/Fixtures by default): what each missed, flagged unmarked, flagged as righteous and flagged as fixed").
		Form("rule schema", "print the JSON Schema of a rule file").
		Form("rule export <folder>", "write the installed rules into a folder, to edit them: a file there at the same path replaces the installed one when COMMANDMENTS_RULES names the folder or it sits beside the binary as rules/").
		Option("--line=N", "with `explain`, only the nodes that start on line N, each with what it holds").
		Option("--work", "with `try`, print how many nodes, checks and analyses the rule cost").
		Option("--all", "with `prove`, prove the shipped rules too, over every fixture project under the path").
		Note("<Rule> is a rule of `.commandments/custom/` by its name — `NoRawSql` or `NoRawSqlDetector` — or the path of a rule file anywhere.").
		Note("A sample marks the code a rule must flag with a comment above it — `// @sin NoRawSqlDetector`, `# @sin …` in Python, `<!-- @sin … -->` in a Vue template — the code it must leave alone with `@righteous NoRawSqlDetector`, and its fix with `@fixed NoRawSqlDetector`. `rule prove` reads `.commandments/custom/samples/` unless given a path, and fails when a rule misses a mark, flags anything unmarked, has no `@sin` mark at all, or cannot be read, and when a mark names no rule of the project.")
}

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
	case "export":
		folder, named := in.Argument(1)
		if !named {
			return help.Usage(console.Err, c, "name the folder to write the installed rules into: `rule export <folder>`."), nil
		}
		written, err := rules.Export(folder)
		if err != nil {
			return 0, err
		}
		console.Write(fmt.Sprintf("%d installed rule files written under %s; point COMMANDMENTS_RULES at it, or put it beside the binary as rules/, to judge with your edits\n", written, folder))

		return 0, nil
	default:
		return help.Usage(console.Err, c, "name what to do: `rule explain`, `rule try`, `rule prove`, `rule schema` or `rule export`."), nil
	}
}

func (c Command) explain(in *cli.Input, console cli.Console) (int, error) {
	path, named := in.Argument(1)
	if !named {
		return help.Usage(console.Err, c, "name the file to explain, e.g. `"+binary.Here()+" rule explain app/Http/OrderController.php`."), nil
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
		return console.Refuse(path + " is no file the tool reads — name one source file: PHP, Vue, TypeScript, Python, C# or Go."), nil
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

func (c Command) try(in *cli.Input, root string, console cli.Console) (int, error) {
	named, given := in.Argument(1)
	paths := in.Arguments()[min(2, len(in.Arguments())):]
	if !given || len(paths) == 0 {
		return help.Usage(console.Err, c, "name the rule and the paths to try it on, e.g. `"+binary.Here()+" rule try NoRawSql app/`."), nil
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

	var languages []source.Language
	for _, judged := range tried.Engines() {
		languages = append(languages, source.OfEngine(judged)...)
	}

	codebase, err := scan.Walk(paths, source.Under(root, project.Excluded)).Only(languages...).Load()
	if err != nil {
		return 0, err
	}

	before := engine.Counted()
	found := engine.ProjectMatches(tried.Find(codebase))
	work := engine.Since(before)
	for _, match := range found {
		console.Write(fmt.Sprintf("%s:%d  %s\n", match.Judged(), match.Line(), sourceLine(match)))
	}

	console.Write(fmt.Sprintf("%d match(es) of %s in %s\n", len(found), tried.Name(), strings.Join(paths, ", ")))
	cli.SayWorkWhen(in.HasFlag("work"), console, work)

	return 0, nil
}

func (c Command) prove(in *cli.Input, root string, console cli.Console) (int, error) {
	path := workspace.SamplesDir(root)
	if given, named := in.Argument(1); named {
		path = given
	}

	if in.HasFlag("all") {
		return c.proveAll(in, root, console)
	}

	own := custom.Load(root)
	for _, reason := range own.Unreadable {
		console.Warn("\033[31m✗\033[0m .commandments/custom/" + reason)
	}

	if len(own.Rules) == 0 {
		return console.Refuse("No rules in .commandments/custom/ to prove — scaffold one with `" + binary.Here() + " make <Name>`."), nil
	}

	if !walk.Exists(path) {
		return console.Refuse("No samples at " + path + " — write files there that mark what each rule must flag with `@sin <Rule>`."), nil
	}

	project, err := config.Load(root)
	if err != nil {
		return 0, err
	}

	var proven []rule.Detector
	var languages []source.Language
	for _, each := range own.Rules {
		proven = append(proven, each.WithLayers(project.Layers(each.Engine())))
		languages = append(languages, source.OfEngine(each.Engine())...)
	}

	codebase, err := scan.Walk([]string{path}, source.Excluded{}).Only(languages...).Load()
	if err != nil {
		return 0, err
	}

	if verified(root, codebase, proven, "is no rule of the project", console) || len(own.Unreadable) > 0 {
		return cli.Refused, nil
	}

	return 0, nil
}

// verified proves each rule against what the codebase's markers say, says each result, and says whether any failed;
// a marker no rule answers to fails, saying what it is no rule of.
func verified(root string, codebase *engine.Codebase, proven []rule.Detector, unknownMarker string, console cli.Console) bool {
	markers := fixture.Markers(codebase)
	failed := false

	var rules []fixture.Rule
	for _, each := range proven {
		rules = append(rules, each)
	}
	for at, result := range (fixture.Fixture{Codebase: codebase, Detectors: rules}).Verify() {
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
				console.Write("    " + reason.says + " " + source.Relative(root, place) + "\n")
			}
		}

		sayWhereAMarkGoes(result, console)
	}

	for _, marker := range markers {
		if slices.ContainsFunc(rules, marker.Naming) {
			continue
		}
		failed = true
		console.Write("\033[31m✗\033[0m " + source.Relative(root, marker.Location) + " marks " + marker.Name + ", which " + unknownMarker + "\n")
	}

	return failed
}

func sayWhereAMarkGoes(result fixture.Result, console cli.Console) {
	if len(result.Missed) == 0 || len(result.Unexpected) == 0 {
		return
	}

	for _, missed := range result.Missed {
		for _, unexpected := range result.Unexpected {
			if fileOf(missed) != fileOf(unexpected) {
				continue
			}

			console.Write("    \033[33m⚠\033[0m a mark and a finding in one file: a `@sin` comment marks the line BELOW it, not the declaration it sits above. If the mark is above a function and the rule flags something inside it, move the mark to just above that line.\n")

			return
		}
	}
}

func fileOf(place string) string {
	return place[:max(strings.LastIndex(place, ":"), 0)]
}

func warnUntwinned(markers []fixture.Marker, asked rule.Detector, console cli.Console) {
	if len(fixture.Marking(markers, fixture.Righteous, asked)) == 0 {
		console.Write("    \033[33m⚠\033[0m no `@righteous` look-alike shows it leaves anything alone\n")
	}
}

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

func Describe(node engine.Match, depth int) string {
	parts := []string{node.Kind()}

	if role := node.Node().InRole(); role != "" {
		parts = []string{role + ": " + node.Kind()}
	}

	if form := node.Node().Form; form != "" {
		parts = append(parts, "form="+form)
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

// proveAll proves every rule in force, shipped and the project's own, against each fixture project under the path:
// each project read whole, so a rule reading across files sees all of it.
func (c Command) proveAll(in *cli.Input, root string, console cli.Console) (int, error) {
	path := filepath.Join(root, "tests", "Fixtures")
	if given, named := in.Argument(1); named {
		path = given
	}
	folders, err := os.ReadDir(path)
	if err != nil {
		return console.Refuse("No fixture projects at " + path + "."), nil
	}
	own := custom.Load(root)
	for _, reason := range own.Unreadable {
		console.Warn("\033[31m✗\033[0m " + reason)
	}
	failed := len(own.Unreadable) > 0
	for _, folder := range folders {
		if !folder.IsDir() {
			continue
		}
		fixtures := filepath.Join(path, folder.Name())
		codebase, err := scan.Walk([]string{fixtures}, source.Excluded{}).Load()
		if err != nil {
			return 0, err
		}
		project, err := config.Load(fixtures)
		if err != nil {
			return 0, err
		}
		enabled, err := project.Enabled(config.Everything)
		if err != nil {
			return 0, err
		}
		for _, each := range own.Rules {
			enabled = append(enabled, each)
		}
		var proven []rule.Detector
		for _, each := range enabled {
			if slices.ContainsFunc(rule.EnginesOf(each), func(engine catalog.Engine) bool { return judgesAny(codebase, engine) }) {
				proven = append(proven, each)
			}
		}
		console.Write(folder.Name() + "\n")
		failed = verified(root, codebase, proven, "is no rule in force", console) || failed
	}
	if failed {
		return cli.Refused, nil
	}

	return 0, nil
}

// judgesAny says whether the codebase holds a file of a language the engine judges.
func judgesAny(codebase *engine.Codebase, judged catalog.Engine) bool {
	return slices.ContainsFunc(codebase.Files(), func(file *engine.File) bool {
		return slices.Contains(source.OfEngine(judged), source.Language(file.Language()))
	})
}
