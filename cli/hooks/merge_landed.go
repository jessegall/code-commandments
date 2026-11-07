package hooks

import (
	"os"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
)

const landedEvent = "suites.coding.workers.worker_worktree.merge"

// landed is a merge the journal has already committed: the branch it landed on, the range it brought in, and who did the work.
type landed struct {
	branch  string
	before  string
	after   string
	worker  string
	task    string
	tasks   []string
	project string
}

func landedOf(line map[string]any) landed {
	fields := eventFields(line)
	row, _ := fields["row"].(map[string]any)
	if row == nil {
		row = fields
	}

	told := toldIn(fields)
	project := told.Project
	if project == "" {
		project = os.Getenv(journalRoot)
	}

	return landed{
		branch:  firstText(told.Branch, asText(row["base_branch"])),
		before:  asText(row["commit_before_merge"]),
		after:   asText(row["commit_after_merge"]),
		worker:  asText(firstOf(row["session_agent_id"], row["worker"])),
		task:    asText(row["task_id"]),
		tasks:   namedFiles(row["merged_task_ids"]),
		project: project,
	}
}

func firstText(said ...string) string {
	for _, each := range said {
		if each != "" {
			return each
		}
	}

	return ""
}

func MergeLanded(line map[string]any) string {
	work := landedOf(line)
	if !mergesJudged() || work.project == "" || work.after == "" {
		return ""
	}

	oneMergeAtATime.Lock()
	defer oneMergeAtATime.Unlock()

	mark := MergedIn(workspace.At(work.project, ""))

	upto, why := mark.UptoUsable(work.project, work.branch, work.after)

	if upto != "" && git.IsAncestor(work.project, work.after, upto) {
		return ""
	}

	from := firstText(upto, work.before)
	if from == "" || from == work.after {
		return ""
	}

	if tooMany := work.tooManyFiles(from); tooMany != "" {
		if err := mark.JudgedForward(work.project, work.branch, work.after); err != nil {
			return ""
		}

		work.keeps(tooMany)

		return (JournalAnswer{Whisper: &tooMany}).Line4()
	}

	marks, narrowed := work.sinsBrought(from)
	if narrowed == couldNotRead {
		return ""
	}

	if err := mark.JudgedForward(work.project, work.branch, work.after); err != nil {
		return ""
	}

	said := work.saidAbout(marks, firstNarrowed(why, narrowed))
	if said == "" {
		return ""
	}

	work.keeps(said)

	sending, refusal := TellsTheWorker(work.project, work.worker, said)
	if sending == ItHasTheWords {
		return ""
	}

	said = work.alsoToldTheAgent(said, sending, refusal)

	return (JournalAnswer{Whisper: &said}).Line4()
}

func (l landed) alsoToldTheAgent(said string, sending Sending, refusal string) string {
	if sending == Retired {
		return said + "\n· " + l.workerNamed() + " could not be told itself, so this is yours: " + refusal
	}

	return said + "\n· " + l.workerNamed() + " should have been told this and was not, so this is yours: " + refusal
}

func (l landed) workerNamed() string {
	if l.worker == "" {
		return "the worker"
	}

	return "worker " + l.worker
}

func (l landed) keeps(said string) {
	if recorded(l.project) {
		Record(bookkeepingIn(l.project), Judged{
			At: time.Now(), Event: landedEvent, Kind: "project", Checkout: l.project,
			Branch: l.branch, Sins: strings.Count(said, "\n"), Said: "the merge",
		})
	}

	MergesIn(workspace.At(l.project, "")).Keeps(l.branch, l.after, said)
}

func (l landed) sinsBrought(from string) ([]SinMark, Narrowed) {
	changed := git.ChangedBetween(l.project, from, l.after)
	if len(changed) == 0 {
		return nil, ""
	}
	if len(changed) > mostFilesAMergeBringsIn {
		return nil, ""
	}

	project, err := config.Load(l.project)
	if err != nil {
		return nil, couldNotRead
	}

	rules := append(enabledIn(project, l.project), custom.InForce(l.project).Enabled(project)...)
	project = project.Judging(rules)

	var files []string
	for _, name := range changed {
		if file := judgedFile(l.project, name, project); file != "" {
			files = append(files, file)
		}
	}

	if len(files) == 0 {
		return nil, ""
	}

	together, _, why := AgainstTheProgramNarrowed(Marks(l.project), files,
		func(file string) []detectors.Detector { return project.RulesFor(l.project, file, rules) },
		func(string) git.ChangedLines { return git.Everywhere() }, l.project)

	single := slices.DeleteFunc(slices.Clone(rules), detectors.ReadsBeyondOneFile)

	var marks []SinMark
	for _, file := range files {
		if found, held := together[file]; held {
			marks = append(marks, found...)

			continue
		}

		found, _, _ := marksIn(Marks(l.project), file, project.RulesFor(l.project, file, single), git.Everywhere())
		marks = append(marks, found...)
	}

	return marks, why
}

func enabledIn(project config.Config, root string) []detectors.Detector {
	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		return nil
	}

	return enabled
}

func (l landed) saidAbout(marks []SinMark, why Narrowed) string {
	if len(marks) == 0 && why == "" {
		return ""
	}

	who := "a worker"
	if l.worker != "" {
		who = "worker " + l.worker
	}

	about := ""
	if l.task != "" {
		about = ", on task " + l.task
	}

	if len(marks) == 0 {
		return Unjudged("what "+who+" merged"+about+" was judged over the range this merge names alone",
			string(why), binary.Judging(l.project)+" --changes")
	}

	said := "Code Commandments: the work " + mergeTold(who+" just merged", about, l.project, marks)

	if why != "" {
		said += "\n\n" + Unjudged("only the range this merge names was judged", string(why), "")
	}

	return said
}

func mergesJudged() bool {
	chosen, given := chosenSettings()
	if !given {
		return true
	}

	said, named := chosen[mergesKey]

	return !named || asText(said) != "false"
}

const mostFilesAMergeBringsIn = 200

func (l landed) tooManyFiles(from string) string {
	brought := len(git.ChangedBetween(l.project, from, l.after))
	if brought <= mostFilesAMergeBringsIn {
		return ""
	}

	return Unjudged("the merge brought in "+strconv.Itoa(brought)+" files, more than a merge is judged in one answer, so none of them was judged here",
		"a merge is answered while the journal waits, and reading that many files is not something to do inside a wait",
		binary.Judging(l.project)+" --branch")
}

var oneMergeAtATime sync.Mutex

const couldNotRead Narrowed = "the project's own config could not be read, so nothing of this merge was judged"

func firstNarrowed(said ...Narrowed) Narrowed {
	for _, each := range said {
		if each != "" {
			return each
		}
	}

	return ""
}
