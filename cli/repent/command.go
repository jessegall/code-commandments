// Package repent is `repent`: run every scribe over the project's source roots, the maintenance rewriters
// and each repentable detector's own fix, until a sweep changes nothing, then write the result or show it as
// a diff.
package repent

import (
	"fmt"
	"github.com/jessegall/code-commandments/cli/binary"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/counter"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/scaffold"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/scribes"
	run "github.com/jessegall/code-commandments/scribes/repent"
	"github.com/jessegall/code-commandments/sins"
)

const (
	// aRuleCouldNotRun is the exit code of a run a scribe broke in.
	aRuleCouldNotRun = 3

	// feedbackEvery is how many runs pass between invitations to report a bad fix.
	feedbackEvery = 4
)

// nothingToFix says a run found nothing a scribe could rewrite, and what that does not mean.
const nothingToFix = "\033[32m✓ Nothing to AUTO-FIX here.\033[0m \033[2mThis is NOT \"no sins\" — it only means no scribe could\n" +
	"  rewrite this scope. A sin `judge` flags without an auto-fixer still stands; fix it BY HAND at its\n" +
	"  source per its skill. Run `judge` to see what remains.\033[0m\n"

// Command is `repent`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"repent"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Auto-fix sins — run every Scribe: the maintenance rewriters (Spatie Data hints) and each Repentable detector's own fix, PHP, Vue and TypeScript, and the fix a project's own rule declares.").
		Form("repent [path]", "apply every fix, re-scanning until it converges (the default — this WRITES)").
		Form("repent [path] --dry-run[=FILE]", "preview a unified diff instead, to the screen or a file").
		Form("repent --only=NAME", "run ONE rewriter (a Scribe or a Repentable detector, lenient name match)").
		Adopt(scope.Options()).
		Option("--dry-run[=FILE]", "preview the rewrite as a unified diff instead of applying it").
		Option("--only=NAME", "run one rewriter only (alias: --sin=NAME)").
		Option("--ignore-package-requirements", "keep package-gated scribes even if this project lacks the package").
		Note("Rewrites only within the source roots `judge` reads (the config's paths), so it never touches " +
			"tests/ or anything judge would not flag. A broken or incorrect repent result is a BUG — report it with `" + binary.Here() + " report`, referencing both the source and the broken output.").
		Note("A rewriter that BREAKS is dropped so every other fix still applies — one bad scribe used to take " +
			"the whole command down. The run then names it and exits 3, because it did not auto-fix everything " +
			"it advertises.")
}

// Fixable maps each sin a scribe repents to the command that runs it, scoped to a checklist when scope is
// given.
func Fixable(checklist string) map[string]string {
	commands := map[string]string{}
	where := ""

	if checklist != "" {
		where = " --repent=" + checklist
	}

	for _, detector := range detectors.All() {
		if _, repentable := detector.(detectors.Repentable); repentable {
			name := detector.Sin().Definition().Name
			commands[name] = binary.Here() + " repent" + where + " --sin=" + name
		}
	}

	return commands
}

// Run rewrites the path the arguments name, or previews it.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	given, named := in.FirstArgument()
	path := strings.TrimRight(given, "/")

	if !named {
		path = "."
	}

	only, filtered := in.Option("only")
	if !filtered {
		only, _ = in.Option("sin")
	}

	if info, err := os.Stat(path); err != nil || !info.IsDir() {
		console.Warn("Not a directory: " + path)

		return 2, nil
	}

	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	project := workspace.ProjectRoot(cwd)
	space := workspace.At(project, "")

	judged, err := config.Load(path)
	if err != nil {
		return 0, err
	}

	targets, err := scope.FromArgs(in.Raw(), path, space, judged.Excluded)
	if scope.IsUnavailable(err) {
		console.Warn(err.Error())

		return 2, nil
	}

	var roots []string

	if named {
		targets = targets.And(scope.Under(path))
		roots, err = config.ToParse(project, path)
	} else {
		roots, err = config.DeclaredRoots(path)
	}

	if err != nil {
		return 0, err
	}

	installed := config.InstalledIn(cwd)
	if in.HasFlag("ignore-package-requirements") {
		installed = config.Everything
	}

	fixers := append(config.Packaged(detectors.All(), installed), custom.Load(project).Enabled(judged)...)

	converged, err := run.Run(roots, targets, filedIn(project), fixers, only)
	if err != nil {
		return 0, err
	}

	if !converged.Settled {
		console.Warn("\033[33m⚠ repent did not settle within " + strconv.Itoa(scribes.MaxSweeps) + " sweeps; applying what converged.\033[0m")
	}

	// PHP's roots are relative to a path left out and absolute for one given, so its labels follow suit.
	base := path
	if !named {
		base = source.Real(path)
	}

	console.Write(leftAlone(project, targets))

	if in.HasFlag("dry-run") {
		file, _ := in.Option("dry-run")

		return preview(base, converged, file, console)
	}

	return apply(base, converged, space, console)
}

// leftAlone names the reported findings standing in the files this run could have fixed, so a reader is not left
// wondering why a sin they can see was not repented. Nothing is said where the project has reported nothing.
func leftAlone(root string, targets scope.Scope) string {
	record, _ := reported.Of(root)

	var issues []string
	for _, row := range record.Rows() {
		if !targets.Includes(filepath.Join(root, row.File)) {
			continue
		}

		if issue := "#" + strconv.Itoa(row.Issue); !slices.Contains(issues, issue) {
			issues = append(issues, issue)
		}
	}

	if len(issues) == 0 {
		return ""
	}

	return "\033[2m↳ " + natural.Counted(len(issues), "reported finding") + " left alone, " +
		strings.Join(issues, ", ") + ": a maintainer is answering the claim, and rewriting the line would " +
		"orphan the report. `" + binary.Here() + " report forget <issue>` takes one back.\033[0m\n"
}

// filedIn is what this project has reported, as the scribes ask it: no scribe rewrites a finding whose claim a
// maintainer is answering, because the fix it would apply may be the very thing the report disputes, and the
// rewrite would change the line's text and orphan the report while its issue is still open.
func filedIn(root string) scribes.Filed {
	record, _ := reported.Of(root)

	return filedRecord{root: root, record: record}
}

// filedRecord answers the scribes from the project's record of what it has reported.
type filedRecord struct {
	root   string
	record reported.Record
}

// IsFiled says whether the rule's finding at this place has been reported as wrong.
func (f filedRecord) IsFiled(rule, file string, line int) bool {
	_, filed := f.record.Reported(rule, f.root, file, line)

	return filed
}

func apply(path string, converged scribes.Converged, space workspace.Workspace, console cli.Console) (int, error) {
	written, err := converged.Files.Apply()
	if err != nil {
		return 0, err
	}

	if len(written) == 0 {
		console.Write(nothingToFix)

		return skipped(converged, console), nil
	}

	files := "files"
	if len(written) == 1 {
		files = "file"
	}

	console.Write("\033[32m✓ Repented " + strconv.Itoa(len(written)) + " " + files + ".\033[0m\n")

	for _, file := range written {
		console.Write("  " + source.Relative(path, file) + "\n")
	}

	if err := scaffoldConstructs(converged.Files, written, console); err != nil {
		return 0, err
	}

	if invite, err := counter.Named(space, "repent-feedback", "rate-limits the post-repent feedback nudge", feedbackEvery).FirstThenEvery(); err == nil && invite {
		console.Write("\033[2m↳ Did that auto-fix do the right thing? If `repent` produced anything broken, incomplete, or\n" +
			"  awkward — or you noticed a rule gap or false positive — file it (don't just work around it):\n" +
			"  `" + binary.Here() + " report --reason=\"…\" --ref=path:line` (referencing the source AND the bad output),\n" +
			"  or `" + binary.Here() + " feature-request --title=\"…\" --reason=\"…\"`.\033[0m\n")
	}

	return skipped(converged, console), nil
}

func preview(path string, converged scribes.Converged, file string, console cli.Console) (int, error) {
	diff, err := scribes.UnifiedDiff(converged.Files, path)
	if err != nil {
		return 0, err
	}

	if diff == "" {
		console.Write(nothingToFix)

		return skipped(converged, console), nil
	}

	if file != "" {
		if err := os.WriteFile(file, []byte(diff), 0o644); err != nil {
			return 0, err
		}

		console.Write("\033[2m↳ dry-run diff written to " + file + "\033[0m\n")
	} else {
		console.Write(diff)
	}

	console.Write("\033[2m↳ Judge THIS diff on its own. The auto-fixers change every release, so never skip a repent from a\n" +
		"  past result or a remembered \"this fix is broken\" — and don't record one as permanently broken;\n" +
		"  re-run `--dry-run` and read the CURRENT output. If a fix is genuinely broken, incomplete, or wrong,\n" +
		"  file it (don't just discard it): `" + binary.Here() + " report --reason=\"…\" --ref=path:line` (source AND bad output).\033[0m\n")

	return skipped(converged, console), nil
}

// skipped says why each scribe that broke broke, names them, and answers the run's exit code.
func skipped(converged scribes.Converged, console cli.Console) int {
	var names judge.Skipped

	for _, step := range converged.Skipped {
		console.Warn(fmt.Sprintf("⚠ %s failed and was skipped — everything else still ran: %v", step.Step, step.Err))
		names = append(names, step.Step)
	}

	if names.IsEmpty() {
		return 0
	}

	console.Write(names.Console() + "\n")

	return aRuleCouldNotRun
}

// scaffoldConstructs scaffolds the helper a rewritten component now uses, such as <SwitchCase>, when the
// project does not have it yet.
func scaffoldConstructs(files scribes.Rewrites, written []string, console cli.Console) error {
	var components strings.Builder

	for _, file := range written {
		if strings.HasSuffix(file, ".vue") {
			components.WriteString(files.Content(file) + "\n")
		}
	}

	for _, detector := range detectors.All() {
		if _, repentable := detector.(detectors.Repentable); !repentable {
			continue
		}

		scaffolding, scaffolds := detector.Sin().(sins.Scaffolding)
		if !scaffolds {
			continue
		}

		for _, helper := range scaffolding.Scaffolds() {
			name := strings.TrimSuffix(helper.Path[strings.LastIndex(helper.Path, "/")+1:], ".vue")

			if strings.Contains(components.String(), "<"+name) {
				if _, err := (scaffold.Command{}).Run(cli.InputOf("scaffold", "--sin="+detector.Sin().Definition().Name), console); err != nil {
					return err
				}

				break
			}
		}
	}

	return nil
}
