package judge

import (
	"errors"
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
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/natural"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const defaultParallel = 2

const agentSession = "JOURNAL_AGENT_SESSION"

const aRuleCouldNotRun = 3

// Command is `judge`: scan a codebase and report its sins, grouped by the skill that fixes each.
type Command struct {
	Fixable map[string]string

	Scaffoldable map[string]string

	In string

	Reading func(sources scan.Sources) (*engine.Codebase, error)

	Spending func(Spent)

	Edited scope.Edits
}

// Spent is where the time of one judging went: reading the sources and running the rules over them.
type Spent struct {
	ReadMs int64
	RuleMs int64
	Rules  int
}

func whyNotServed(answer warm.Answer) string {
	if answer.Because != "" {
		return answer.Because
	}

	return "no service answered, so there is none up for this project"
}

func (c Command) folder() (string, error) {
	if c.In != "" {
		return c.In, nil
	}

	return os.Getwd()
}

func (c Command) read(sources scan.Sources) (*engine.Codebase, error) {
	if c.Reading != nil && sources.Count(source.Languages...) > 0 {
		return c.Reading(sources)
	}

	return sources.Load()
}

func (Command) Names() []string {
	return []string{"judge"}
}

func (Command) Help() help.Help {
	return help.Of("Scan a codebase and report its sins, grouped by the skill that fixes each. Exit code 1 when sins are found, 3 when a rule could not run.").
		Form("judge [path]", "scan a folder, or one file read with the folder it sits in — or, with none, the source roots declared in .commandments/config.json").
		Form("judge --list", "list every detector, grouped by skill").
		Option("--list", "list every detector grouped by the skill that fixes it, and run none of them").
		Option("--skill=NAME", "only run detectors for one skill (group), e.g. spatie-data").
		Option("--sin=NAME", "only run detectors for one sin (lenient name match), e.g. nullable-callback").
		Option("--tag=TAG", "only run the project's own rules carrying one of these tags, repeated or comma-separated, e.g. --tag testing").
		Option("--exclude=A,B", "skip findings in paths containing any fragment").
		Option("--one-file-rules", "run only the rules that answer from one file, naming the rest as not asked; "+
			"the running service uses it when the tree cannot answer the whole question").
		Adopt(scope.Options()).
		Option("--parallel=N", "run detectors across N worker processes (default: 2, capped at cores; 1 = off)").
		Option("--ignore-package-requirements", "keep package-gated rules even if this project lacks the package (cross-project calibration)").
		Option("--checklist=FILE", "write the checklist here (default: your session's sins/sins.md, in .commandments/sessions/<id>/ or the journal plugin's data folder)").
		Option("--no-checklist", "print only, don't write the checklist file").
		Option("--benchmark", "time each detector and print the slowest").
		Option("--profile=FILE", "with --benchmark, write a CPU profile of the detectors to FILE, read with `go tool pprof`").
		Note("With no [path], judge scans the source roots declared under \"paths\" in " +
			".commandments/config.json — auto-detected on first run from your composer.json PSR-4 map (plus " +
			"app/src), so scaffolding like database/, storage/ and config/ is not judged. Run `commandments config " +
			"reindex` to re-detect them, or pass an explicit [path] to scan it directly. Add a path under " +
			"\"exclude\" to subtract it from ANY run: that tree is not read at all, so nothing in it is " +
			"ever reported or rewritten. One rule may be let in anyway with a \"judges\" call under it in " +
			"\"configure\" — `{\"configure\": {\"NoRawSqlDetector\": [{\"judges\": [\"tests\"]}]}}` reads tests/ for " +
			"that rule and no other.").
		Note("A rule that BREAKS is skipped so the rest of the run survives — but the run is not green: it " +
			"names the rules that could not run and exits 3 (rather than 0) when nothing else was found, " +
			"because a run missing a rule has not judged what that rule judges. A bridge that stops partway " +
			"is treated the same: the files it read and every other language are judged, and the run names it.").
		Note("Judge writes a Markdown checklist into your session folder (the run prints the exact path). " +
			"A full scan is slow, so judge ONCE and work that file line-by-line, deleting each line as you fix its sin; re-run judge at the end to confirm. Files marked @code-commandments-generated are skipped — they are regenerated, not hand-authored.")
}

func (c Command) Run(in *cli.Input, console cli.Console) (int, error) {
	cwd, err := c.folder()
	if err != nil {
		return 0, err
	}

	space := workspace.At(workspace.ProjectRoot(cwd), "")
	worklist := worklistSpace(space, cwd)
	options := optionsOf(in, worklist)

	options.path = within(cwd, options.path)

	if c.Reading == nil && !options.list {
		serving, said := warm.ServingFor(cwd)
		if !said {
			serving.Project = space.Root()
		}

		answer, asked := warm.AskedUntilTheTreeIsThere(serving.Project, warm.Request{Judge: in.Raw(), In: cwd, Progress: cli.Watched(), Agent: os.Getenv(agentSession) != ""},
			func(said string) { fmt.Fprintln(console.Err, "· "+said) },
			func(line string) { fmt.Fprintln(console.Err, line) })
		if asked {
			if cli.Timed() {
				fmt.Fprintln(console.Err, "· judged against the tree this session keeps up, so this run read nothing")
			}

			fmt.Fprint(console.Out, answer.Out)
			fmt.Fprint(console.Err, answer.Err)

			return answer.Code, nil
		}

		if cli.Timed() {
			fmt.Fprintln(console.Err, "· this run reads the tree itself: "+whyNotServed(answer))
		}
	}

	if options.list {
		return list(cwd, console)
	}

	if remembered, keeps := RememberedFor(space.Root(), console.Warn); keeps && c.Reading == nil {
		remembered.Load()
		defer remembered.Save()
	}

	if extra, named := in.Argument(1); named {
		return help.Usage(console.Err, c, "judge takes one path; "+extra+" would never be judged. Name a folder that holds both, or run judge once for each."), nil
	}

	info, err := os.Stat(options.path)
	if err != nil {
		console.Warn("✗ Nothing to judge at " + options.path)

		return 2, nil
	}

	file := ""
	if !info.IsDir() {
		file, options.path = options.path, filepath.Dir(options.path)
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

	own := custom.InForce(cwd)
	console.Warn(own.Warnings(project)...)
	enabled = append(enabled, own.Enabled(project)...)
	options.unreadableRules = own.Unreadable

	selected := selectFrom(enabled, options.skill, options.sin, options.tags)

	if options.oneFileRules {
		selected = slices.DeleteFunc(slices.Clone(selected), detectors.ReadsBeyondOneFile)
	}

	if len(selected) == 0 {
		console.Warn("✗ No detector matched --skill=" + options.skill + " --sin=" + options.sin + " --tag=" + strings.Join(options.tags, ","))

		return 2, nil
	}

	configuredAt := options.path
	if c.In != "" {
		configuredAt = git.ProjectRoot(c.In)
	}

	judged, err := config.Load(configuredAt)
	if err != nil {
		return 0, err
	}

	judged = judged.Judging(selected)

	targets, err := c.Edited.Scope(in.Raw(), options.path, worklist, judged.Excluded)

	if cli.Timed() {
		fmt.Fprintf(console.Err, "· the scope was built over %s, leaving out %v\n", options.path, judged.Excluded)
	}

	if scope.IsUnavailable(err) {
		console.Warn(err.Error())

		return 2, nil
	}

	if file != "" {
		targets = targets.NarrowedTo(file)
	}

	if options.pathGiven {
		targets = targets.And(givenFolder(options.path))
	}

	return c.judge(options, selected, judged, targets, space, console)
}

func (c Command) judge(options options, selected []detectors.Detector, judged config.Config, targets scope.Scope, space workspace.Workspace, console cli.Console) (int, error) {
	if !options.parallelGiven && judged.Parallel > 0 {
		options.parallel = judged.Parallel
	}

	if targets.IsEmpty() {
		deleteChecklist(options.checklist)

		return choseNone(targets, console), nil
	}

	roots, err := sourceRoots(options, judged)
	if err != nil {
		return 0, err
	}

	languages := languagesFor(selected, judged, options.path)

	if ofNoLanguage(targets, languages) {
		deleteChecklist(options.checklist)

		return choseNone(targets, console), nil
	}

	languages = reaching(targets, languages)

	progress := cli.NewProgress(console.Err)
	progress.Status("parsing")
	parsing := time.Now()

	sources := scan.Walk(roots, judged.Exclusions(options.path)).Only(languages...)

	if len(targets.Files()) > 0 {
		if across := readAcrossFiles(selected); len(across) > 0 {
			sayWhyTheWholeTreeIsRead(across, len(selected), len(targets.Files()), console)
		} else {
			sources = sources.JustThese(targets.Includes)
		}
	}

	whole, byProject := wholeAndByProject(selected)

	read := sources.Only(slices.DeleteFunc(slices.Clone(languages), func(language source.Language) bool { return language == source.CSharp })...)
	progress.Plan(read.Bridged())
	codebase, err := c.read(read.Reporting(progress.Phase("reading")))
	parseSeconds := time.Since(parsing).Seconds()
	var incomplete scan.Incomplete
	if errors.As(err, &incomplete) {
		err = nil
	}
	if err != nil {
		progress.Finish()

		return 0, err
	}

	progress.Restart(len(whole))

	tasks := make([]Task, len(whole))

	for i, detector := range whole {
		tasks[i] = Task{detector, codebase}
	}

	judgement := c.run(tasks, options, progress, parseSeconds, console)
	judgement.Skipped = joined(judgement.Skipped, incomplete.Bridges...)
	judgement.Skipped = joined(judgement.Skipped, readNothing(read, codebase)...)
	csharpFiles := 0

	if len(byProject) > 0 {
		projects, files, err := c.judgeUnits(sources, byProject, options, progress, console)
		if err != nil {
			return 0, err
		}

		judgement.Findings = append(judgement.Findings, projects.Findings...)
		judgement.Skipped = joined(judgement.Skipped, projects.Skipped...)
		csharpFiles = files
	}

	judgement.Findings = keep(asWalked(judgement.Findings, sources.GivenOf()), options.exclude, targets, judged)

	if space.IsJournalDriven() {
		if err := dashboard.Record(space, judgement.Findings, judgedPaths(targets, sources)); err != nil {
			return 0, err
		}
	}

	active, filed, readRecord := apart(space.Root(), judgement.Findings)
	if !readRecord {
		console.Warn("✗ " + reported.Path(space.Root()) + " could not be read, so every reported sin is judged as " +
			"an active one. Put it back as the record it was, or delete it and report what is still wrong again.")
	}
	judgement.Findings = active

	skipped := Skipped(joined(judgement.Skipped, options.unreadableRules...))

	if len(judgement.Findings) == 0 {
		if len(filed) == 0 {
			deleteChecklist(options.checklist)
		}

		scanned := judgedFiles(sources, csharpFiles, scannedLanguages(selected))
		chosen := scanned
		if targets.IsScoped() {
			chosen = sources.Within(targets, scannedLanguages(selected)...)
		}
		if targets.IsScoped() && chosen == 0 {
			return choseNone(targets, console), nil
		}

		console.Say(skipped.Verdict(nothingActive(filed) + " in " + judgedCount(targets, chosen, scanned)))
		console.Say(unseenNote(targets)...)

		for _, target := range options.checklist {
			if len(filed) > 0 {
				write(target, NewReport(options.path, nil, c.Fixable, c.Scaffoldable, skipped).Reporting(filed).Checklist(), space, console)
			}
		}

		if skipped.IsEmpty() {
			return 0, nil
		}

		console.Say(skipped.Console())

		return aRuleCouldNotRun, nil
	}

	report := NewReport(options.path, judgement.Findings, c.Fixable, c.Scaffoldable, skipped).Saying(saying(selected)).Reporting(filed)
	if cli.Watched() {
		console.Say(report.Said())
	} else {
		console.Say(report.Console())
	}

	if journal := os.Getenv(cli.JournalReport); journal != "" {
		if err := os.WriteFile(journal, report.Journal(), 0o644); err != nil {
			console.Warn("✗ The journal's report could not be written: " + err.Error())
		}
	}
	console.Say(unseenNote(targets)...)

	for _, target := range options.checklist {
		write(target, report.Checklist(), space, console)
	}

	return 1, nil
}

func nothingActive(filed []Reported) string {
	if len(filed) == 0 {
		return "No sins found"
	}

	return "No active sins, " + natural.Counted(len(filed), "reported finding") + " aside,"
}

const chosenNoneInUnseen = 2

func choseNone(targets scope.Scope, console cli.Console) int {
	if len(targets.Unseen()) == 0 {
		return console.Say("\033[32m✓ Nothing to judge: no source file under the path is among " + targets.ChosenBy() + ".\033[0m")
	}
	console.Warn("✗ Nothing was judged: no source file under the path is among " + targets.ChosenBy() + ". Only the repository the path lies in was asked; the repositories under it were not: " + strings.Join(targets.Unseen(), ", ") + ". Name one: --branch=<repo>:<branch> or --pr=<repo>#<n>.")

	return chosenNoneInUnseen
}

func judgedCount(targets scope.Scope, chosen, scanned int) string {
	files := strconv.Itoa(chosen) + " " + plural(chosen, "file", "files")
	if !targets.IsScoped() {
		return files
	}
	if targets.ChosenBy() == "" {
		return files + " of " + strconv.Itoa(scanned) + " read"
	}

	return files + ", " + targets.ChosenBy() + ", of " + strconv.Itoa(scanned) + " read"
}

func unseenNote(targets scope.Scope) []string {
	if len(targets.Unseen()) == 0 {
		return nil
	}

	return []string{"Not looked into: " + strings.Join(targets.Unseen(), ", ") + ", repositories of their own under the path; name one with --branch=<repo>:<branch> or --pr=<repo>#<n>."}
}

func plural(count int, one, many string) string {
	if count == 1 {
		return one
	}

	return many
}

func saying(selected []detectors.Detector) map[string]string {
	says := map[string]string{}
	for _, rule := range selected {
		definition := rule.Sin().Definition()
		says[definition.Name] = definition.Description
	}

	return says
}

func (c Command) run(tasks []Task, options options, progress *cli.Progress, parseSeconds float64, console cli.Console) Judgement {
	if !options.benchmark {
		judging := time.Now()
		before := engine.Counted()
		judgement := Run(tasks, options.parallel, progress, console.Err)
		progress.Finish()
		sayWhereTheTimeWent(parseSeconds, time.Since(judging).Seconds(), len(tasks), engine.Since(before), console)

		if c.Spending != nil {
			c.Spending(Spent{ReadMs: int64(parseSeconds * 1000), RuleMs: time.Since(judging).Milliseconds(), Rules: len(tasks)})
		}

		return judgement
	}

	stop := profiled(options.profile, console)
	judgement, profiles := Benchmark(tasks, console.Err)
	stop()
	progress.Finish()
	fmt.Fprint(console.Err, Profiles(profiles, parseSeconds))

	return judgement
}

func selectFrom(list []detectors.Detector, skill, sin string, tags []string) []detectors.Detector {
	var kept []detectors.Detector

	for _, detector := range list {
		definition := detector.Sin().Definition()

		if skill != "" && !strings.Contains(catalog.Normalise(definition.Slug()), catalog.Normalise(skill)) {
			continue
		}

		if sin != "" && !definition.Scopes(sin) {
			continue
		}

		if len(tags) > 0 && !carriesOneOf(detector, tags) {
			continue
		}

		kept = append(kept, detector)
	}

	return kept
}

func tagsOf(in *cli.Input) []string {
	var tags []string
	for _, given := range in.Repeated("tag") {
		tags = append(tags, cli.Commas(given)...)
	}

	return tags
}

func carriesOneOf(detector detectors.Detector, tags []string) bool {
	tagged, carries := detector.(detectors.Tagged)
	if !carries {
		return false
	}

	for _, tag := range tagged.Tags() {
		if slices.Contains(tags, tag) {
			return true
		}
	}

	return false
}

func writtenIn(engines []catalog.Engine) []source.Language {
	var languages []source.Language
	for _, engine := range engines {
		for _, language := range source.OfEngine(engine) {
			if !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

func readAlongside(engines []catalog.Engine) []source.Language {
	languages := writtenIn(engines)
	for _, engine := range engines {
		for _, language := range publishers[engine] {
			if !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

var publishers = map[catalog.Engine][]source.Language{
	catalog.PHP:        {source.Vue, source.TypeScript},
	catalog.Vue:        {source.PHP},
	catalog.TypeScript: {source.PHP},
}

func languagesFor(selected []detectors.Detector, project config.Config, root string) []source.Language {
	var languages []source.Language

	for _, detector := range selected {
		for _, language := range readAlongside(detectors.EnginesOf(detector)) {
			if project.Reads(root, language) && !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

func readAcrossFiles(selected []detectors.Detector) []string {
	var across []string
	for _, detector := range selected {
		if catalog.IsCrossFile(detector) {
			across = append(across, catalog.Name(detector))
		}
	}

	return across
}

func sayWhyTheWholeTreeIsRead(across []string, selected, judged int, console cli.Console) {
	reason := strconv.Itoa(len(across)) + " of the " + strconv.Itoa(selected) + " rules read across files"
	if len(across) == 1 {
		reason = across[0] + " reads across files"
	}

	fmt.Fprintf(console.Err, "· reading the whole path, not only the %d %s judged: %s, so the scope chose what is reported, not what is read\n",
		judged, plural(judged, "file", "files"), reason)
}

func reaching(targets scope.Scope, languages []source.Language) []source.Language {
	files := targets.Files()
	if len(files) == 0 {
		return languages
	}

	var changed []source.Language
	for path := range files {
		if language := source.OfFile(path); !slices.Contains(changed, language) {
			changed = append(changed, language)
		}
	}

	var reads []source.Language
	for _, language := range languages {
		if slices.Contains(changed, language) || publishesFor(language, changed) {
			reads = append(reads, language)
		}
	}

	return reads
}

func publishesFor(language source.Language, changed []source.Language) bool {
	for engine, published := range publishers {
		if !slices.Contains(published, language) {
			continue
		}

		for _, reads := range source.OfEngine(engine) {
			if slices.Contains(changed, reads) {
				return true
			}
		}
	}

	return false
}

func ofNoLanguage(targets scope.Scope, languages []source.Language) bool {
	files := targets.Files()
	if len(files) == 0 || len(languages) == 0 {
		return false
	}

	for path := range files {
		if slices.Contains(languages, source.OfFile(path)) {
			return false
		}
	}

	return true
}

func scannedLanguages(selected []detectors.Detector) []source.Language {
	languages := []source.Language{source.PHP}

	for _, detector := range selected {
		for _, language := range writtenIn(detectors.EnginesOf(detector)) {
			if !slices.Contains(languages, language) {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

func judgedFiles(sources scan.Sources, csharpFiles int, languages []source.Language) int {
	judged := 0

	for _, language := range languages {
		if language == source.CSharp {
			judged += csharpFiles

			continue
		}
		judged += sources.Count(language)
	}

	return judged
}

func readNothing(sources scan.Sources, codebase *engine.Codebase) []string {
	var silent []string
	for _, bridge := range scan.Bridges {
		if slices.Contains(bridge.Languages, source.CSharp) {
			continue
		}

		if sources.Count(bridge.Languages...) == 0 {
			continue
		}

		written := make([]contract.Language, 0, len(bridge.Languages))
		for _, language := range bridge.Languages {
			written = append(written, language.Written())
		}

		if len(codebase.Of(written...).Files()) > 0 {
			continue
		}

		silent = append(silent, "the "+bridge.Label+" bridge")
	}

	return silent
}

func wholeAndByProject(rules []detectors.Detector) (whole, byProject []detectors.Detector) {
	for _, rule := range rules {
		if detectors.Judges(rule, catalog.CSharp) {
			byProject = append(byProject, rule)
		} else {
			whole = append(whole, rule)
		}
	}

	return whole, byProject
}

func givenFolder(path string) scope.Within {
	project := mirrored(path)
	if project == "" {
		return scope.Within{Folders: []string{path}}
	}

	return scope.Within{Folders: []string{path, warm.Counterpart(git.Root(path), project, path)}}
}

func sourceRoots(options options, judged config.Config) ([]string, error) {
	if options.pathGiven {
		return []string{strings.TrimRight(options.path, "/")}, nil
	}

	return judged.WalkedRoots(options.path)
}

func judgedPaths(targets scope.Scope, sources scan.Sources) map[string]bool {
	if files := targets.Files(); files != nil {
		return files
	}

	walked := map[string]bool{}

	for file := range sources.GivenOf() {
		walked[file] = true
	}

	return walked
}

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

func keep(findings []engine.Finding, exclude []string, targets scope.Scope, judged config.Config) []engine.Finding {
	var kept []engine.Finding

	for _, finding := range findings {
		if isExcluded(finding.File, exclude) {
			continue
		}
		if !targets.Covers(finding.File, finding.Line) && !judgesItsFolder(judged, finding, targets) {
			continue
		}

		kept = append(kept, finding)
	}

	return kept
}

func judgesItsFolder(judged config.Config, finding engine.Finding, targets scope.Scope) bool {
	folders := append(
		judged.Judged(config.RuleNamed(config.Detector, finding.Detector)),
		judged.Judged(config.RuleNamed(config.Detector, finding.Engine+"/"+finding.Detector))...,
	)
	if len(folders) == 0 {
		return false
	}
	if !targets.CoversJudged(finding.File, finding.Line) {
		return false
	}

	return slices.ContainsFunc(folders, func(folder string) bool { return inFolder(finding.File, folder) })
}

func inFolder(file, folder string) bool {
	return strings.Contains(file, "/"+folder+"/") || strings.HasPrefix(file, folder+"/")
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

func list(cwd string, console cli.Console) (int, error) {
	project, err := config.Load(cwd)
	if err != nil {
		return 0, err
	}

	enabled, err := project.Enabled(config.InstalledIn(cwd))
	if err != nil {
		return 0, err
	}

	enabled = append(config.InClassOrder(config.Detector, enabled), custom.InForce(cwd).Enabled(project)...)
	bySkill := map[string][]string{}

	for _, detector := range enabled {
		slug := detector.Sin().Definition().Slug()
		name := catalog.Name(detector)

		if custom.Owns(detector) {
			name += " (custom)"
		}

		bySkill[slug] = append(bySkill[slug], name)
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

	console.Say("", "\033[1;33mlanguages read here\033[0m", "  "+strings.Join(languagesRead(cwd, project), ", "))

	return 0, nil
}

func languagesRead(root string, project config.Config) []string {
	var named []string
	for _, language := range source.Languages {
		if project.Reads(root, language) {
			named = append(named, language.Label())
		}
	}

	return named
}

// options are what the arguments ask of a run.
type options struct {
	unreadableRules []string
	path            string
	pathGiven       bool
	skill           string
	sin             string
	list            bool
	exclude         []string
	checklist       []string
	parallel        int
	parallelGiven   bool
	benchmark       bool
	profile         string
	ignore          bool
	oneFileRules    bool
	tags            []string
}

func within(folder, path string) string {
	if path == "" || filepath.IsAbs(path) {
		return path
	}

	return filepath.Clean(filepath.Join(folder, path))
}

func worklistSpace(space workspace.Workspace, cwd string) workspace.Workspace {
	checkout := git.Root(cwd)
	if checkout == "" || !space.IsJournalDriven() {
		return space
	}

	return workspace.At(checkout, "")
}

func optionsOf(in *cli.Input, space workspace.Workspace) options {
	path, given := in.FirstArgument()
	if !given {
		path = "."
	}

	skill, _ := in.Option("skill")
	sin, _ := in.Option("sin")
	parallel := defaultParallel
	value, parallelGiven := in.Option("parallel")

	if parallelGiven {
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
		path:          strings.TrimRight(path, "/"),
		pathGiven:     given,
		skill:         skill,
		sin:           sin,
		list:          in.HasFlag("list"),
		exclude:       in.List("exclude"),
		checklist:     targets,
		parallel:      max(1, parallel),
		parallelGiven: parallelGiven,
		benchmark:     in.HasFlag("benchmark"),
		profile:       profileOf(in),
		ignore:        in.HasFlag("ignore-package-requirements"),
		oneFileRules:  in.HasFlag("one-file-rules"),
		tags:          tagsOf(in),
	}
}

func sayWhereTheTimeWent(reading, judging float64, rules int, work engine.Work, console cli.Console) {
	if !cli.Timed() {
		return
	}

	fmt.Fprintf(console.Err, "· read in %.2fs, judged in %.2fs by %s\n", reading, judging, natural.Counted(rules, "rule"))
	cli.SayWork(console, work)
}
