package scan

import (
	"testing"
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
