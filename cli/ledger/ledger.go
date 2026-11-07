package ledger

import (
	"strconv"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

var legend = &state.Legend{
	About: "Code-commandments — what the judging service has cost this project: every read of a whole tree and every judging it answered, counted, so a test or a person can see whether a run read the tree or waited for it.",
	Variables: []state.Variable{
		{Name: TreeReadsStarted, Meaning: "how many times the service began reading a whole tree"},
		{Name: TreeReadsFinished, Meaning: "how many of those reads finished and were kept"},
		{Name: TreeReadsFailed, Meaning: "how many of those reads failed"},
		{Name: FilesReadIntoTrees, Meaning: "the files all finished reads parsed between them"},
		{Name: JudgingsFromTheTree, Meaning: "judgings the service answered from a tree it held"},
		{Name: JudgingsToldTheTreeIsComing, Meaning: "judgings told to ask again because a read of the tree was running"},
		{Name: JudgingsDeclined, Meaning: "judgings the service declined, so the run read the files itself"},
		{Name: JudgingsAnsweredSmaller, Meaning: "judgings answered by the rules that read one file only, because the tree could not carry them"},
	},
	List: "one event per line: the unix time in milliseconds, a tab, what happened, a tab, the checkout or project it was for",
	Safe: "the counts start again from nothing; nothing reads them but tests and a person",
}

const (
	TreeReadsStarted            = "tree_reads_started"
	TreeReadsFinished           = "tree_reads_finished"
	TreeReadsFailed             = "tree_reads_failed"
	FilesReadIntoTrees          = "files_read_into_trees"
	JudgingsFromTheTree         = "judgings_from_the_tree"
	JudgingsToldTheTreeIsComing = "judgings_told_the_tree_is_coming"
	JudgingsDeclined            = "judgings_declined"
	JudgingsAnsweredSmaller     = "judgings_answered_smaller"
)

const eventsKept = 500

var writing sync.Mutex

// Ledger is what the judging service has cost one project, kept in that project's cache.
type Ledger struct {
	file state.File
}

func Of(project string) Ledger {
	return Ledger{state.At(workspace.At(project, "").Cache(".ledger"), legend)}
}

func (l Ledger) Counts(name string, by int, where string) {
	writing.Lock()
	defer writing.Unlock()

	read := l.file.Read()

	events := append(read.Items(), strconv.FormatInt(time.Now().UnixMilli(), 10)+"\t"+name+"\t"+where)
	if len(events) > eventsKept {
		events = events[len(events)-eventsKept:]
	}

	_ = l.file.Write(read.With(state.Int(name, read.Int(name, 0)+by)).WithItems(events))
}

func Names() []string {
	names := make([]string, len(legend.Variables))
	for at, variable := range legend.Variables {
		names[at] = variable.Name
	}

	return names
}

func (l Ledger) Count(name string) int {
	return l.file.Read().Int(name, 0)
}

func (l Ledger) Events() []string {
	return l.file.Read().Items()
}
