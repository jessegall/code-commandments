package scan

import (
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

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

	if project, found := projectOf([]string{ours, theirs}); found {
		t.Errorf("a run over two projects names %q", project)
	}
}

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
