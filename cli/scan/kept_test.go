package scan

import (
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

// TestTheProjectIsTheOneTheRootsLieIn holds which service a run belongs to. The journal names the checkout it
// started the run in, which is right for a run that reads that checkout and wrong for one pointed at another
// project: an agent judging a tree beside its own named its own project, found no service there for those roots
// and paid a whole cold bridge, measured at 39 seconds for one Python file of a 5286-file tree against 8.7 the
// moment the right project was named.
func TestTheProjectIsTheOneTheRootsLieIn(t *testing.T) {
	ours, theirs := repository(t), repository(t)

	t.Setenv("JOURNAL_ROOT", ours)
	t.Setenv("CLAUDE_PROJECT_DIR", "")

	if project, found := projectOf([]string{ours}); !found || project != ours {
		t.Errorf("reading our own project names %q, found %v", project, found)
	}

	if project, found := projectOf([]string{theirs}); !found || project != theirs {
		t.Errorf("reading another project names %q, found %v; it is that project's service that knows that tree", project, found)
	}

	// A run spanning two projects belongs to neither, since no one service holds both trees.
	if project, found := projectOf([]string{ours, theirs}); found {
		t.Errorf("a run over two projects names %q", project)
	}
}

// TestABigAskDoesNotTakeTheWarmBridge holds what the bridge a session keeps warm is for. It serves every judging
// on the machine one ask at a time, so a run that hands it a tree holds up every edit behind it: measured on a
// consumer's tree, one small file waited 61 seconds inside the bridge while a worker's hand-in judged its whole
// worktree through the same session. An edit's ask is one file and must still share it.
func TestABigAskDoesNotTakeTheWarmBridge(t *testing.T) {
	one := bridge.Request{Paths: []string{"/project"}, Write: []string{"/project/src/order.py"}}
	if !shares(one) {
		t.Error("an edit's ask of one file does not share the warm bridge, which is what it is for")
	}

	var tree []string
	for i := 0; i <= sharedAsk; i++ {
		tree = append(tree, "/project/src/file.py")
	}

	if shares(bridge.Request{Paths: []string{"/project"}, Write: tree}) {
		t.Errorf("an ask of %d files takes the warm bridge, holding up every edit behind it", len(tree))
	}
}
