package judge

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/checklist"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// defaultParallel is how many workers judge runs detectors across unless --parallel says otherwise.
const defaultParallel = 2

// aRuleCouldNotRun is the exit code of a run that found nothing while a rule never ran.
const aRuleCouldNotRun = 3

// Command is `judge`: scan a codebase and report its sins, grouped by the skill that fixes each.
type Command struct {
	// Fixable maps a sin to the command that repents it.
	Fixable map[string]string

	// Scaffoldable maps a sin to the command that scaffolds the helper its fix uses.
	Scaffoldable map[string]string
}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"judge"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Scan a codebase and report its sins, grouped by the skill that fixes each. Exit code 1 when sins are found, 3 when a rule could not run.").
		Form("judge [path]", "scan a path — or, with none, the source roots declared in .commandments/config.php").
		Form("judge --list", "list every detector, grouped by skill").
		Option("--list", "list every detector grouped by the skill that fixes it, and run none of them").
		Option("--skill=NAME", "only run detectors for one skill (group), e.g. spatie-data").
		Option("--sin=NAME", "only run detectors for one sin (lenient name match), e.g. nullable-callback").
		Option("--exclude=A,B", "skip findings in paths containing any fragment").
		Adopt(scope.Options()).
		Option("--parallel=N", "run detectors across N worker processes (default: 2, capped at cores; 1 = off)").
		Option("--ignore-package-requirements", "keep package-gated rules even if this project lacks the package (cross-project calibration)").
		Option("--checklist=FILE", "write the checklist here (default: your session's sins/sins.md, in .commandments/sessions/<id>/ or the journal plugin's data folder)").
		Option("--no-checklist", "print only, don't write the checklist file").
		Option("--benchmark", "time each detector and print the slowest").
		Note("With no [path], judge scans the source roots declared by $config->paths(...) in " +
			".commandments/config.php — auto-detected on first run from your composer.json PSR-4 map (plus " +
			"app/src), so scaffolding like database/, storage/ and config/ is not judged. Run `commandments config " +
			"reindex` to re-detect them, or pass an explicit [path] to scan it directly. Add " +
			"$config->exclude('app/Generated') to subtract a path from ANY run — the tree is still parsed (so " +
			"cross-file rules stay correct) but nothing in it is ever reported or rewritten.").
		Note("A rule that BREAKS is skipped so the rest of the run survives — but the run is not green: it " +
			"names the rules that could not run and exits 3 (rather than 0) when nothing else was found, " +
			"because a run missing a rule has not judged what that rule judges.").
		Note("Judge writes a Markdown checklist into your session folder (the run prints the exact path). " +
			"A full scan is slow, so judge ONCE and work that file line-by-line, deleting each line as you fix its sin; re-run judge at the end to confirm. Files marked @code-commandments-generated are skipped — they are regenerated, not hand-authored.")
}

// Run judges the path the arguments name.
func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	space := workspace.At(workspace.ProjectRoot(cwd), "")
	options := optionsOf(in, space)

	if options.list {
		return list(cwd, console)
	}

	if info, err := os.Stat(options.path); err != nil || !info.IsDir() {
		console.Warn("Not a directory: " + options.path)

		return 2, nil
	}

	project, err := config.Load(cwd)
	if err != nil {
		return 0, err
	}

	installed := config.InstalledIn(cwd)
	if options.ignore {
		installed = config.Everything
	}

	enabled, err := project.Enabled(installed)
	if err != nil {
		return 0, err
	}

	selected := selectFrom(enabled, options.skill, options.sin)

	if len(selected) == 0 {
		console.Warn("No detector matched --skill=" + options.skill + " --sin=" + options.sin)

		return 2, nil
	}

	judged, err := config.Load(options.path)
	if err != nil {
		return 0, err
	}

	targets, err := scope.FromArgs(in.Raw(), options.path, space, judged.Excluded)

	if scope.IsUnavailable(err) {
		console.Warn(err.Error())

		return 2, nil
	}

	return c.judge(options, selected, judged, targets, space, console)
}

func (c Command) judge(options options, selected []detectors.Detector, judged config.Config, targets scope.Scope, space workspace.Workspace, console cli.Console) (int, error) {
	if targets.IsEmpty() {
		deleteChecklist(options.checklist)

		return console.Say("\033[32m✓ No changed files to judge.\033[0m"), nil
	}

	roots, err := sourceRoots(options)
	if err != nil {
		return 0, err
	}

	progress := cli.NewProgress(console.Err)
	progress.Status("parsing")
	parsing := time.Now()

	sources := scan.Walk(roots, source.Under(options.path, judged.Excluded)).Only(languagesFor(selected, judged)...)

	codebase, err := sources.Load()
	parseSeconds := time.Since(parsing).Seconds()
	if err != nil {
		progress.Finish()

		return 0, err
	}

	tasks := make([]Task, len(selected))

	for i, detector := range selected {
		tasks[i] = Task{detector, codebase}
	}

	judgement := c.run(tasks, options, progress, parseSeconds, console)
	judgement.Findings = keep(asWalked(judgement.Findings, sources.GivenOf()), options.exclude, targets)

	if space.IsJournalDriven() {
		if err := dashboard.Record(space, judgement.Findings, targets.Files()); err != nil {
			return 0, err
		}
	}

	skipped := Skipped(judgement.Skipped)

	if len(judgement.Findings) == 0 {
		deleteChecklist(options.checklist)

		scanned := judgedFiles(sources, codebase, scannedLanguages(selected))
		files := "files"
		if scanned == 1 {
			files = "file"
		}

		console.Say("\033[32m✓ No sins found in " + strconv.Itoa(scanned) + " " + files + ".\033[0m")

		if skipped.IsEmpty() {
			return 0, nil
		}

		console.Say(skipped.Console())

		return aRuleCouldNotRun, nil
	}

	report := NewReport(options.path, judgement.Findings, c.Fixable, c.Scaffoldable, skipped)
	console.Say(report.Console())

	for _, target := range options.checklist {
		write(target, report.Checklist(), space, console)
	}

	return 1, nil
}

// run runs the tasks across the workers, or one by one and timed under --benchmark.
func (Command) run(tasks []Task, options options, progress *cli.Progress, parseSeconds float64, console cli.Console) Judgement {
	if !options.benchmark {
		judgement := Run(tasks, options.parallel, progress, console.Err)
		progress.Finish()

		return judgement
	}

	judgement, profiles := Benchmark(tasks, console.Err)
	progress.Finish()
	fmt.Fprint(console.Err, Profiles(profiles, parseSeconds))

	return judgement
}

// selectFrom narrows to one skill (a lenient slug match) and one sin (its name or its skill's slug).
func selectFrom(list []detectors.Detector, skill, sin string) []detectors.Detector {
	var kept []detectors.Detector

	for _, detector := range list {
		definition := detector.Sin().Definition()

		if skill != "" && !strings.Contains(catalog.Normalise(definition.Slug()), catalog.Normalise(skill)) {
			continue
		}

		if sin != "" && !definition.Scopes(sin) {
			continue
		}

		kept = append(kept, detector)
	}

	return kept
}

// engineLanguages are the languages each engine's rules read.
var engineLanguages = map[catalog.Engine][]source.Language{
	catalog.Backend:    {source.PHP},
	catalog.Frontend:   {source.Vue, source.TypeScript},
	catalog.TypeScript: {source.Vue, source.TypeScript},
	catalog.Python:     {source.Python},
	catalog.CSharp:     {source.CSharp},
}

// publishers are the languages whose facts an engine's rules read beside their own: the frontend asks
// what the server publishes, and the backend what the frontend reads back.
var publishers = map[catalog.Engine][]source.Language{
	catalog.Backend:    {source.Vue, source.TypeScript},
	catalog.Frontend:   {source.PHP},
	catalog.TypeScript: {source.PHP},
}

// languagesFor are the languages the selected rules read, less those the project does not write.
func languagesFor(selected []detectors.Detector, project config.Config) []source.Language {
	var languages []source.Language

	for _, detector := range selected {
		engine, _ := detectors.EngineOf(detector)

		for _, language := range append(slices.Clone(engineLanguages[engine]), publishers[engine]...) {
			if project.Writes(language) && !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

// scannedLanguages are the languages a run counts as judged: PHP always, and every other language whose
// rules ran.
func scannedLanguages(selected []detectors.Detector) []source.Language {
	languages := []source.Language{source.PHP}

	for _, detector := range selected {
		engine, _ := detectors.EngineOf(detector)

		for _, language := range engineLanguages[engine] {
			if !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

// judgedFiles is how many of the languages' files the run judged: C#'s are the files its bridge wrote, since
// without the bridge none is read, and every other language's are the files the walk found.
func judgedFiles(sources scan.Sources, codebase *engine.Codebase, languages []source.Language) int {
	judged := 0

	for _, language := range languages {
		if language != source.CSharp {
			judged += sources.Count(language)

			continue
		}
		for _, file := range codebase.Files() {
			if file.Language() == contract.CSharp {
				judged++
			}
		}
	}

	return judged
}

// sourceRoots are what the run scans: the path it was given, or the roots the config declares.
func sourceRoots(options options) ([]string, error) {
	if options.pathGiven {
		return []string{strings.TrimRight(options.path, "/")}, nil
	}

	return config.DeclaredRoots(options.path)
}

// asWalked names every finding's file, location and twins by the path its file was walked as.
func asWalked(findings []engine.Finding, given scan.Given) []engine.Finding {
	for i, finding := range findings {
		findings[i].File = given.Of(finding.File)
		findings[i].Location = locationAsWalked(finding.Location, given)

		for j, twin := range finding.Twins {
			findings[i].Twins[j] = locationAsWalked(twin, given)
		}
	}

	return findings
}

func locationAsWalked(location string, given scan.Given) string {
	at := strings.LastIndex(location, ":")
	if at < 0 {
		return location
	}

	return given.Of(location[:at]) + location[at:]
}

// keep are the findings the run reports: in no excluded path fragment, and in scope.
func keep(findings []engine.Finding, exclude []string, targets scope.Scope) []engine.Finding {
	var kept []engine.Finding

	for _, finding := range findings {
		if isExcluded(finding.File, exclude) || !targets.Includes(finding.File) {
			continue
		}

		kept = append(kept, finding)
	}

	return kept
}

func isExcluded(path string, exclude []string) bool {
	for _, fragment := range exclude {
		if fragment != "" && strings.Contains(path, fragment) {
			return true
		}
	}

	return false
}

func write(target, contents string, space workspace.Workspace, console cli.Console) {
	if !checklist.Prepare(target, space) {
		console.Warn("Could not create the checklist folder " + filepath.Dir(target) + " — the findings above were not written.")

		return
	}

	checklist.At(target).Archive()

	if os.WriteFile(target, []byte(contents), 0o644) != nil {
		console.Warn("Could not write the checklist to " + target + " — the findings above are all there is.")

		return
	}

	console.Say("\033[2m↳ checklist written to " + target + " — fix each item, then delete its line\033[0m")
}

func deleteChecklist(targets []string) {
	for _, target := range targets {
		checklist.At(target).Delete()
	}
}

// list prints every detector that would run here, grouped under the skill that fixes it.
func list(cwd string, console cli.Console) (int, error) {
	project, err := config.Load(cwd)
	if err != nil {
		return 0, err
	}

	enabled, err := project.Enabled(config.InstalledIn(cwd))
	if err != nil {
		return 0, err
	}

	bySkill := map[string][]string{}

	for _, detector := range enabled {
		slug := detector.Sin().Definition().Slug()
		bySkill[slug] = append(bySkill[slug], catalog.Name(detector))
	}

	skills := make([]string, 0, len(bySkill))

	for slug := range bySkill {
		skills = append(skills, slug)
	}

	sort.Strings(skills)

	for _, slug := range skills {
		console.Say("\033[1;33m" + slug + "\033[0m")

		for _, name := range bySkill[slug] {
			console.Say("  " + name)
		}
	}

	return 0, nil
}

// options are what the arguments ask of a run.
type options struct {
	path      string
	pathGiven bool
	skill     string
	sin       string
	list      bool
	exclude   []string
	checklist []string
	parallel  int
	benchmark bool
	ignore    bool
}

func optionsOf(in *cli.Input, space workspace.Workspace) options {
	path, given := in.FirstArgument()
	if !given {
		path = "."
	}

	skill, _ := in.Option("skill")
	sin, _ := in.Option("sin")
	parallel := defaultParallel

	if value, set := in.Option("parallel"); set {
		parallel = cli.Intval(value)
	}

	var targets []string

	if _, repenting := scope.Repent(in.Raw()); !in.HasFlag("no-checklist") && !repenting {
		target, set := in.Option("checklist")
		if !set {
			target = space.Checklist()
		}

		targets = []string{target}
	}

	return options{
		path:      strings.TrimRight(path, "/"),
		pathGiven: given,
		skill:     skill,
		sin:       sin,
		list:      in.HasFlag("list"),
		exclude:   in.List("exclude"),
		checklist: targets,
		parallel:  max(1, parallel),
		benchmark: in.HasFlag("benchmark"),
		ignore:    in.HasFlag("ignore-package-requirements"),
	}
}
