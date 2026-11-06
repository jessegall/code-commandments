package hooks

import (
	"os"
	"strconv"
	"strings"

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

	mark := MergedIn(workspace.At(work.project, ""))

	from := firstText(mark.Upto(work.branch), work.before)
	if from == "" || from == work.after {
		return ""
	}

	marks := work.sinsBrought(from)

	if err := mark.Judged(work.branch, work.after); err != nil {
		return ""
	}

	if len(marks) == 0 {
		return ""
	}

	said := work.saidAbout(marks)

	return (JournalAnswer{Whisper: &said}).Line4()
}

func (l landed) sinsBrought(from string) []SinMark {
	changed := git.ChangedBetween(l.project, from, l.after)
	if len(changed) == 0 {
		return nil
	}

	project, err := config.Load(l.project)
	if err != nil {
		return nil
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
		return nil
	}

	together, _, _ := AgainstTheProgramNarrowed(Marks(l.project), files,
		func(file string) []detectors.Detector { return project.RulesFor(l.project, file, rules) },
		func(string) git.ChangedLines { return git.Everywhere() }, l.project)

	var marks []SinMark
	for _, file := range files {
		marks = append(marks, together[file]...)
	}

	return marks
}

func enabledIn(project config.Config, root string) []detectors.Detector {
	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		return nil
	}

	return enabled
}

func (l landed) saidAbout(marks []SinMark) string {
	var named []string
	for _, mark := range marks {
		named = append(named, mark.ShownFrom(l.project))
	}

	who := "a worker"
	if l.worker != "" {
		who = "worker " + l.worker
	}

	about := ""
	if l.task != "" {
		about = ", on task " + l.task
	}

	return "Code Commandments: the work " + who + " just merged" + about + " brings in " +
		strconv.Itoa(len(marks)) + " sin(s), each to fix at its source:\n  " + strings.Join(named, "\n  ")
}

func mergesJudged() bool {
	chosen, given := chosenSettings()
	if !given {
		return true
	}

	said, named := chosen[mergesKey]

	return !named || asText(said) != "false"
}
