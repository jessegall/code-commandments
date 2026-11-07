package hooks

import (
	"fmt"
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
	beingFilled    Narrowed = "what this checkout differs from its branch by is being read now"
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
			rebuilt, err := scan.Walk(toRead, source.Excluded{}).Load()
			if err != nil || rebuilt == nil {
				return nil, time.Since(reading), parseFailed
			}

			streams = append(streams, rebuilt.Streams()...)
		}

		read = time.Since(reading)

		if why := languageInTheTree(base, file); why != "" {
			return nil, read, why
		}

		if checkout == strings.TrimRight(held.project, "/") {
			view = base.ViewFrom(checkout, streams, held.project, nil)

			kept.Patched(base, view, marks)
		} else {
			overlaid, why := overlaid(kept.Over(checkout), base, held, fresh, streams, marks, time.Now())
			if why != "" {
				return nil, read, why
			}

			view = overlaid
		}
	}

	judged := slices.DeleteFunc(slices.Clone(files), func(path string) bool { return !holds(view, path) })
	if len(judged) == 0 {
		return nil, read, notInTheTree
	}

	if len(judged) != len(files) {
		return marksOverTogether(view, marking, judged, rulesFor, changedFor), read, notInTheTree
	}

	return marksOverTogether(view, marking, judged, rulesFor, changedFor), read, ""
}

func overlaid(over *warm.Overlay, base *warm.Base, held branchRoot, files []string, streams []*contract.Stream, marks map[string]warm.Known, now time.Time) (*engine.Codebase, Narrowed) {
	if !over.FilledAt(git.Head(over.Checkout)) {
		over.Empties()
	}

	if state, _, _ := over.Filling(); state != warm.Filled {
		if _, _, past := kept.PastItsCeiling(); past {
			return nil, pastTheCeiling
		}
	}

	if state, _, _ := over.Filling(); state != warm.Filled {
		if why := fill(over, held, files...); why != "" {
			return nil, why
		}
	}

	if over.SweepDue(now) {
		over.Removed(removedIn(held, over.Checkout))
	}

	over.Patch(streams, marks)

	return base.ViewFrom(over.Checkout, over.Streams(), held.project, over.Gone()), ""
}

func fill(over *warm.Overlay, held branchRoot, files ...string) Narrowed {
	if !over.BeginsFilling() {
		return beingFilled
	}

	changed, told := git.ChangedVsBranch(over.Checkout, held.branch)
	if !told {
		over.CouldNotFill(string(cannotList))

		return cannotList
	}

	judged := judgesIn(held.project, over.Checkout)

	var reading []string
	for path := range changed {
		if slices.Contains(files, path) {
			continue
		}

		if !judged(path) {
			continue
		}

		reading = append(reading, path)
	}

	for _, path := range alsoDirtyInTheProject(over.Checkout, held, files, reading) {
		reading = append(reading, path)
	}

	if len(reading) == 0 {
		over.Fills(time.Now(), git.Head(over.Checkout))

		return ""
	}

	taken := warm.Taken(reading)

	rebuilt, err := scan.Walk(reading, source.Excluded{}).Load()
	if err != nil || rebuilt == nil {
		over.CouldNotFill(string(fillingFailed))

		return fillingFailed
	}

	over.Patch(rebuilt.Streams(), taken)
	over.Fills(time.Now(), git.Head(over.Checkout))

	return ""
}

func alsoDirtyInTheProject(checkout string, held branchRoot, files, reading []string) []string {
	var also []string

	for file := range git.ChangedVsHead(held.project) {
		under, inside := warm.Under(held.project, file)
		if !inside {
			continue
		}

		stands := filepath.Join(checkout, under)
		if stands == file {
			continue
		}

		if slices.Contains(files, stands) || slices.Contains(reading, stands) || slices.Contains(also, stands) {
			continue
		}

		if info, err := os.Stat(stands); err != nil || info.IsDir() {
			continue
		}

		also = append(also, stands)
	}

	return also
}

func removedIn(held branchRoot, checkout string) []string {
	deleted, told := git.DeletedVsBranch(checkout, held.branch)
	if !told {
		return nil
	}

	var gone []string
	for _, under := range deleted {
		gone = append(gone, filepath.Join(held.project, under))
	}

	return gone
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
		rebuilt, err := scan.Walk(reading_, source.Excluded{}).Load()
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
			if detectors.Judges(rule, source.OfFile(file).Engine()) {
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
		if !detectors.Judges(rule, language.Engine()) {
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

	if _, already := reading.LoadOrStore(named.key(), true); already {
		return
	}

	go func() {
		defer reading.Delete(named.key())

		roots, err := config.DeclaredRoots(named.project)
		if err != nil || len(roots) == 0 {
			roots = []string{named.project}
		}

		began := time.Now()
		costs := ledger.Of(named.project)
		costs.Counts(ledger.TreeReadsStarted, 1, named.project)

		sources := scan.Walk(roots, source.Under(named.project, project.Excluded)).Only(reads(named.project, project)...)

		taken := warm.Taken(sources.Files(source.Languages...))

		codebase, err := sources.Load()
		if err != nil || codebase == nil {
			costs.Counts(ledger.TreeReadsFailed, 1, named.project)
			lastRead.Store(named.key(), fmt.Sprintf("the last read of it failed after %v: %v",
				time.Since(began).Round(time.Second), err))

			return
		}

		costs.Counts(ledger.TreeReadsFinished, 1, named.project)
		costs.Counts(ledger.FilesReadIntoTrees, len(codebase.Files()), named.project)
		lastRead.Store(named.key(), fmt.Sprintf("it was last read in %v, %d files, at %s",
			time.Since(began).Round(time.Second), len(codebase.Files()), time.Now().Format("15:04:05")))

		kept.Hold(named.project, named.branch, roots, codebase, taken)
	}()
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

	if state, _, _ := kept.Over(checkout).Filling(); state == warm.BeingFilled {
		return named, nil, beingFilled
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
	if err != nil || len(settings.Paths) == 0 {
		return func(string) bool { return true }
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
	return why == noTreeHeld || why == noTreeComing || why == beingFilled
}

func TheTreeIsComing(root, checkout string) (Narrowed, bool) {
	named, _, why := answering(root, checkout)
	if !waitingOn(why) {
		return why, false
	}

	if why == beingFilled {
		return why, true
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
