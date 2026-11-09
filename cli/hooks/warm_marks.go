package hooks

import (
	"errors"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

var kept = keeping()

func keeping() *warm.Keeper {
	held := warm.Keep()
	held.Leaving(func(checkout string) { parsed.leaves(checkout) })

	return held
}

var reading sync.Map

var lastRead sync.Map

func howTheReadWent(named branchRoot) (string, bool) {
	if _, running := reading.Load(named.key()); running {
		return "a read of it is running now", true
	}

	said, tried := lastRead.Load(named.key())
	if !tried {
		return "", false
	}

	return said.(string), true
}

// Narrowed is why a judging could not put the project's whole set of rules to a file: empty where it could. The
// rules that read across files cannot answer from one file alone, so a narrowed judging is a smaller answer, and
// an answer nobody is told is smaller reads as a clean file.
type Narrowed string

const (
	noTreeHeld     Narrowed = "no tree of this checkout is held yet, and one is being read now"
	cannotList     Narrowed = "git could not say what this checkout differs from its branch by"
	fillingFailed  Narrowed = "what this checkout differs from its branch by could not be read"
	checkoutGone   Narrowed = "this checkout is no longer on disk"
	noTreeComing   Narrowed = "no tree of this checkout is held and none is being read, so only the rules that answer from one file will ever run here"
	filesNotOnDisk Narrowed = "what changed is not on disk"
	noneToJudge    Narrowed = "nothing of this edit is a file this project judges"
	tooMuchMoved   Narrowed = "more files changed than one view can carry, so the tree was let go"
	patchFailed    Narrowed = "the held tree could not be brought up to what changed"
	parseFailed    Narrowed = "what changed could not be parsed"
	notInTheTree   Narrowed = "what changed is not in the tree that is held"
	pastTheCeiling Narrowed = "the service already holds as much of this project as it is allowed to"
)

func noStreamOf(language source.Language) Narrowed {
	said := "the tree held for this project has no " + language.Label() + " in it, so only the rules that answer from one file can run here"

	if language == source.CSharp {
		said += ", and C# is read per checkout rather than per file, so its project's own types are not in that answer"
	}

	return Narrowed(said)
}

func AgainstTheProgram(marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines, root string) ([]SinMark, time.Duration, bool) {
	together, read, why := AgainstTheProgramNarrowed(marking, []string{file},
		func(string) []detectors.Detector { return rules },
		func(string) git.ChangedLines { return changed }, root)

	return together[file], read, why == ""
}

func AgainstTheProgramTogether(marking Marking, files []string, rulesFor func(string) []detectors.Detector,
	changedFor func(string) git.ChangedLines, root string) (map[string][]SinMark, time.Duration, bool) {
	together, read, why := AgainstTheProgramNarrowed(marking, files, rulesFor, changedFor, root)

	return together, read, why == ""
}

func AgainstTheProgramNarrowed(marking Marking, files []string, rulesFor func(string) []detectors.Detector,
	changedFor func(string) git.ChangedLines, root string) (map[string][]SinMark, time.Duration, Narrowed) {

	if len(files) == 0 {
		return nil, 0, noneToJudge
	}

	file := files[0]

	checkout := checkoutOf(root, file)

	kept.CutAt(checkout, marking.Cut)

	held, base, why := answering(root, checkout)
	if why != "" {
		return nil, 0, why
	}

	here := slices.DeleteFunc(slices.Clone(files), func(path string) bool { return warm.Mark(path) == "" })
	if len(here) == 0 {
		return nil, 0, filesNotOnDisk
	}

	files, file = here, here[0]

	moved, swept := base.StaleSince(time.Now(), files...)

	if swept {
		moved = append(moved, base.Unheld(unannouncedIn(held.project))...)
	}
	if len(moved) > readsInOneGo(base.Files()) {
		return nil, 0, tooMuchMoved
	}

	elsewhere := slices.DeleteFunc(slices.Clone(moved), func(path string) bool { return slices.Contains(files, path) })
	if len(elsewhere) > 0 {
		patched, took := taken(base, held, elsewhere)
		if patched == nil {
			kept.Drop(held.project, held.branch)
			letGo.Store(held.project, patchFailed)

			return nil, took, patchFailed
		}

		base = patched
	}

	view := base.Codebase()
	var read time.Duration

	fresh := slices.DeleteFunc(slices.Clone(files), func(path string) bool { return !slices.Contains(moved, path) })

	if len(fresh) > 0 {
		reading := time.Now()

		marks := map[string]warm.Known{}
		for _, path := range fresh {
			marks[path] = warm.Now(path)
		}

		var streams []*contract.Stream
		var toRead []string

		for _, path := range fresh {
			if already, has := parsed.streamsOf(path); has {
				streams = append(streams, already...)

				continue
			}

			toRead = append(toRead, path)
		}

		if len(toRead) > 0 {
			trees, _, err := treesOf(held.project, checkout, toRead)
			if err != nil || trees == nil {
				return nil, time.Since(reading), parseFailed
			}

			streams = append(streams, trees...)
		}

		read = time.Since(reading)

		if nothingCameOutOf(streams) {
			return nil, read, parseFailed
		}

		if why := languageInTheTree(base, file); why != "" {
			return nil, read, why
		}

		if checkout == strings.TrimRight(held.project, "/") {
			view = base.ViewFrom(checkout, streams, held.project, nil)

			kept.Patched(base, view, marks)
		} else {
			over, why := overTheCheckout(base, held, checkout, fresh, streams)
			if why != "" {
				return nil, read, why
			}

			view = over
		}
	}

	judged := slices.DeleteFunc(slices.Clone(files), func(path string) bool { return !holds(view, path) })
	if len(judged) == 0 {
		return nil, read, notInTheTree
	}

	costs := ledger.Of(held.project)
	costs.Counts(ledger.JudgingsFromTheTree, 1, checkout)

	before := engine.Counted()
	found := marksOverTogether(view, marking, judged, rulesFor, changedFor)
	countDetectorFiles(costs, before, checkout)

	if len(judged) != len(files) {
		return found, read, notInTheTree
	}

	return found, read, ""
}

func countDetectorFiles(costs ledger.Ledger, before engine.Work, checkout string) {
	did := engine.Since(before)
	costs.Counts(ledger.DetectorFilesJudged, int(did.Judged), checkout)
	costs.Counts(ledger.DetectorFilesRemembered, int(did.Remembered), checkout)
}

func nothingCameOutOf(streams []*contract.Stream) bool {
	for _, stream := range streams {
		for _, file := range stream.Files {
			if file.Errors > 0 && len(file.Root.Children) == 0 {
				return true
			}
		}
	}

	return false
}

func overTheCheckout(base *warm.Base, held branchRoot, checkout string, files []string, streams []*contract.Stream) (*engine.Codebase, Narrowed) {
	if _, _, past := kept.PastItsCeiling(); past {
		return nil, pastTheCeiling
	}

	differing, gone, told := differsFromTheBase(base, held, checkout)
	if !told {
		return nil, cannotList
	}

	others := slices.DeleteFunc(differing, func(path string) bool { return slices.Contains(files, path) })

	known, _, err := treesOf(held.project, checkout, others)

	var incomplete scan.Incomplete
	if err != nil && !errors.As(err, &incomplete) {
		return nil, fillingFailed
	}

	return base.ViewFrom(checkout, slices.Concat(streams, known), held.project, gone), ""
}

func differsFromTheBase(base *warm.Base, held branchRoot, checkout string) (read, gone []string, told bool) {
	changed, deleted, told := git.DiffersFromTip(checkout, held.branch)
	if !told {
		return nil, nil, false
	}

	for _, under := range deleted {
		gone = append(gone, filepath.Join(held.project, under))
	}

	for path := range changed {
		read = append(read, path)
	}

	for _, file := range slices.Concat(base.Moved(), unannouncedIn(held.project)) {
		under, inside := warm.Under(held.project, file)
		if !inside {
			continue
		}

		stands := filepath.Join(checkout, under)
		if warm.Mark(stands) == "" {
			gone = append(gone, file)

			continue
		}

		read = append(read, stands)
	}

	judged := judgesIn(held.project, checkout)
	read = slices.DeleteFunc(read, func(path string) bool { return !judged(path) })
	slices.Sort(read)

	return slices.Compact(read), gone, true
}

func unannouncedIn(project string) []string {
	var named []string
	for file := range git.ChangedVsHead(project) {
		named = append(named, file)
	}

	return named
}

func taken(base *warm.Base, held branchRoot, files []string) (*warm.Base, time.Duration) {
	reading := time.Now()
	marks := map[string]warm.Known{}

	for _, file := range files {
		marks[file] = warm.Now(file)
	}

	var reading_ []string
	var gone []string

	for _, file := range files {
		if warm.Mark(file) == "" {
			gone = append(gone, file)

			continue
		}

		reading_ = append(reading_, file)
	}

	streams := []*contract.Stream(nil)
	if len(reading_) > 0 {
		rebuilt, err := loadCounted(held.project, scan.Walk(reading_, source.Excluded{}))
		if err != nil || rebuilt == nil {
			return nil, time.Since(reading)
		}

		streams = rebuilt.Streams()
	}

	view := base.ViewFrom(held.project, streams, held.project, gone)

	return kept.Patched(base, view, marks), time.Since(reading)
}

func holds(codebase *engine.Codebase, file string) bool {
	for _, held := range codebase.Files() {
		if held.Path == file {
			return true
		}
	}

	return false
}

func marksOverTogether(codebase *engine.Codebase, marking Marking, files []string,
	rulesFor func(string) []detectors.Detector, changedFor func(string) git.ChangedLines) map[string][]SinMark {

	marks := map[string][]SinMark{}
	if len(files) == 0 {
		return marks
	}

	asksFor := map[string][]string{}
	var rules []detectors.Detector

	for _, file := range files {
		for _, rule := range rulesFor(file) {
			named := detectors.IdentityOf(rule)
			if _, known := asksFor[named]; !known {
				rules = append(rules, rule)
			}

			asksFor[named] = append(asksFor[named], file)
		}
	}

	narrowed := codebase.Reporting(files...)

	for _, rule := range rules {
		began := time.Now()
		before := engine.Counted()
		judges := map[string]bool{}
		for _, file := range asksFor[detectors.IdentityOf(rule)] {
			if detectors.JudgesAny(rule, source.OfFile(file).Engines()) {
				judges[file] = true
			}
		}

		if len(judges) == 0 {
			continue
		}

		asked := narrowed
		if _, weighs := rule.(detectors.Aggregating); weighs {
			asked = codebase
		}

		for _, match := range safely(rule, asked) {
			if !judges[match.File()] {
				continue
			}

			marks[match.File()] = append(marks[match.File()], marking.Of(rule, match, changedFor(match.File())))
		}

		timed(rule, time.Since(began), engine.Since(before))
	}

	return marks
}

func marksOver(codebase *engine.Codebase, marking Marking, file string, rules []detectors.Detector, changed git.ChangedLines) []SinMark {
	narrowed := codebase.Reporting(file)
	language := source.OfFile(file)

	var marks []SinMark

	for _, rule := range rules {
		if !detectors.JudgesAny(rule, language.Engines()) {
			continue
		}

		asked := narrowed
		if _, weighs := rule.(detectors.Aggregating); weighs {
			asked = codebase
		}

		for _, match := range safely(rule, asked) {
			if match.File() != file {
				continue
			}

			marks = append(marks, marking.Of(rule, match, changed))
		}
	}

	return marks
}

var serving atomic.Bool

func Serves() {
	serving.Store(true)
	kept.Ceiling(ceilingChosen())
}

func Warming(root string, project config.Config) {
	if !serving.Load() {
		return
	}

	named, ok := rootOf(root)
	if !ok {
		return
	}

	if _, held := kept.Holds(named.project, named.branch); held {
		return
	}

	progress := &treeRead{project: named.project, branch: named.branch, began: time.Now()}
	if _, already := reading.LoadOrStore(named.key(), progress); already {
		return
	}

	go func() {
		defer reading.Delete(named.key())

		roots, err := project.WalkedRoots(named.project)
		if err != nil || len(roots) == 0 {
			roots = []string{named.project}
		}

		began := time.Now()
		costs := ledger.Of(named.project)
		folder := savedTreeFolder(named)

		sources := scan.Walk(roots, project.Exclusions(named.project)).Only(reads(named.project, project)...).
			Reporting(progress.reached)

		files := sources.Files(source.Languages...)

		logStarted(fmt.Sprintf("tree load | project=%s | branch=%s | files=%d", named.project, named.branch, len(files)))

		warm.SweepSaved(filepath.Dir(folder), time.Now())

		saved, refused := warm.LoadSaved(folder, thisBuild(), roots, time.Now())
		if refused == nil && broughtBackFromDisk(named, roots, files, saved, folder, began) {
			return
		}

		if refused != nil && !errors.Is(refused, fs.ErrNotExist) {
			logWarned(fmt.Sprintf("the saved tree at %s is not used, so the tree starts empty | %v", folder, refused))
		}

		costs.Counts(ledger.TreeReadsStarted, 1, named.project)

		taken := warm.Taken(files)

		codebase, err := loadCounted(named.project, sources)
		if err != nil || codebase == nil {
			costs.Counts(ledger.TreeReadsFailed, 1, named.project)
			lastRead.Store(named.key(), fmt.Sprintf("the last read of it failed after %v: %v",
				time.Since(began).Round(time.Second), err))

			return
		}

		costs.Counts(ledger.TreeReadsFinished, 1, named.project)
		costs.Counts(ledger.FilesReadIntoTrees, len(codebase.Files()), named.project)
		lastRead.Store(named.key(), fmt.Sprintf("it was last read in %v, %d files, at %s (the saved tree was not used: %v)",
			time.Since(began).Round(time.Second), len(codebase.Files()), time.Now().Format("15:04:05"), refused))

		savesWhileHeld(kept.Hold(named.project, named.branch, roots, codebase, taken), named, folder)
	}()
}

func broughtBackFromDisk(named branchRoot, roots, files []string, saved warm.Saved, folder string, began time.Time) bool {
	codebase := engine.Load(saved.Streams...)
	codebase.Scanned(roots...)
	codebase.Rooted(named.project)

	moved := movedSince(saved.Marks, files)
	if len(moved) > readsInOneGo(len(codebase.Files())) {
		return false
	}

	base := kept.Hold(named.project, named.branch, roots, codebase, saved.Marks)

	if len(moved) > 0 {
		patched, _ := taken(base, named, moved)
		if patched == nil {
			kept.Drop(named.project, named.branch)

			return false
		}

		savesWhileHeld(patched, named, folder)
	}

	ledger.Of(named.project).Counts(ledger.TreesLoadedFromDisk, 1, named.project)
	lastRead.Store(named.key(), fmt.Sprintf("it was loaded from disk in %v, %d files, %d of them caught up, at %s",
		time.Since(began).Round(time.Millisecond), len(codebase.Files()), len(moved), time.Now().Format("15:04:05")))

	return true
}

func savesWhileHeld(base *warm.Base, named branchRoot, folder string) {
	if base.Save(folder, thisBuild(), time.Now()) == nil {
		treeSaves.wrote(base)
	}

	if _, held := kept.Holds(named.project, named.branch); !held {
		os.RemoveAll(folder)
	}
}

func movedSince(marks map[string]warm.Known, files []string) []string {
	var moved []string
	here := make(map[string]bool, len(files))

	for _, file := range files {
		here[file] = true

		if known, held := marks[file]; !held || !known.Stands(file) {
			moved = append(moved, file)
		}
	}

	for file := range marks {
		if !here[file] {
			moved = append(moved, file)
		}
	}

	return moved
}

func savedTreeFolder(named branchRoot) string {
	return filepath.Join(workspace.At(named.project, "").StateDir(), "saved-trees", workspace.KeyFor(named.key()))
}

func reads(root string, project config.Config) []source.Language {
	var languages []source.Language
	for _, language := range source.Languages {
		if language != source.CSharp && project.Reads(root, language) {
			languages = append(languages, language)
		}
	}

	return languages
}

// branchRoot is the base a checkout answers from: the project's main checkout and the branch it is on.
type branchRoot struct {
	project string
	branch  string
}

func (b branchRoot) key() string {
	return b.project + "\x00" + b.branch
}

func rootOf(path string) (branchRoot, bool) {
	project := git.ProjectRoot(path)
	if project == "" {
		return branchRoot{}, false
	}

	branch := git.CurrentBranch(project)
	if branch == "" {
		return branchRoot{}, false
	}

	if branch == "HEAD" {
		return branchRoot{}, false
	}

	return branchRoot{project: project, branch: branch}, true
}

func answering(path, checkout string) (branchRoot, *warm.Base, Narrowed) {
	if info, err := os.Stat(checkout); err != nil || !info.IsDir() {
		return branchRoot{}, nil, checkoutGone
	}

	named, base, held := baseFor(path)
	own := named.project != "" && checkout == strings.TrimRight(named.project, "/")

	if held {
		return named, base, ""
	}

	if _, _, past := kept.PastItsCeiling(); past {
		return named, nil, pastTheCeiling
	}

	if own || named.project == "" {
		return named, nil, whatIsComing(named)
	}

	any, holds := kept.Any(named.project)
	if !holds {
		return named, nil, whatIsComing(named)
	}

	return branchRoot{project: named.project, branch: any.Branch}, any, ""
}

func baseFor(path string) (branchRoot, *warm.Base, bool) {
	named, ok := rootOf(path)
	if !ok {
		return branchRoot{}, nil, false
	}

	base, held := kept.Holds(named.project, named.branch)

	return named, base, held
}

func cutOf(event Event) string {
	if cut := event.Cut(); cut != "" {
		return cut
	}

	return git.CurrentBranch(checkoutOf(event.Root, event.Root))
}

func languageInTheTree(base *warm.Base, file string) Narrowed {
	language := source.OfFile(file)
	if language == "" {
		return ""
	}

	if slices.Contains(base.Languages(), language.Written()) {
		return ""
	}

	return noStreamOf(language)
}

func judgesIn(project, checkout string) func(string) bool {
	settings, err := config.Load(projectOf(project))
	if err != nil {
		return func(string) bool { return true }
	}

	if len(settings.Paths) == 0 {
		excluded := settings.Exclusions(checkout)

		return func(path string) bool { return !excluded.Covers(path) }
	}

	return func(path string) bool {
		return settings.IsJudged(checkout, path)
	}
}

func WarmsWhatItServes(root string) {
	project, err := config.Load(root)
	if err != nil {
		return
	}

	kept.DropsEveryBranchBut(git.ProjectRoot(root), git.CurrentBranch(root))

	Warming(root, project)
}

func waitingOn(why Narrowed) bool {
	return why == noTreeHeld || why == noTreeComing
}

func TheTreeIsComing(root, checkout string) (Narrowed, bool) {
	named, _, why := answering(root, checkout)
	if !waitingOn(why) {
		return why, false
	}

	WarmsWhatItServes(root)

	if !aReadIsRunning(named) {
		return why, false
	}

	return noTreeHeld, true
}

func whatIsComing(named branchRoot) Narrowed {
	if !serving.Load() {
		return noTreeComing
	}

	if !aReadIsRunning(named) {
		return noTreeComing
	}

	return noTreeHeld
}

func aReadIsRunning(named branchRoot) bool {
	if named.key() == "" {
		return false
	}

	_, running := reading.Load(named.key())

	return running
}

// treeRead is one read of a whole tree while it runs: what it reads, since when, and how many of its files are read so far.
type treeRead struct {
	project string
	branch  string
	began   time.Time
	done    atomic.Int64
	total   atomic.Int64
}

func (r *treeRead) reached(done, total int) {
	r.done.Store(int64(done))
	r.total.Store(int64(total))
}

func (r *treeRead) percent() int64 {
	total := r.total.Load()
	if total == 0 {
		return 0
	}

	return r.done.Load() * 100 / total
}

func readsRunning() []*treeRead {
	var running []*treeRead

	reading.Range(func(_, value any) bool {
		if read, is := value.(*treeRead); is {
			running = append(running, read)
		}

		return true
	})

	return running
}
