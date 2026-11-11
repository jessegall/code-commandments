package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
)

func TestAnEditNamesItsAgentAsAnEditorOfItsCheckout(t *testing.T) {
	edits := WorkerEditsIn(t.TempDir())

	edits.KeepIn(7, "/checkout/one", "src/mine.py")
	edits.KeepIn(8, "/checkout/two", "src/theirs.py")

	if said := edits.EditorsIn("/checkout/one"); !slices.Equal(said, []int{7}) {
		t.Fatalf("the editors of the checkout are %v", said)
	}
}

func TestApprovingKeepsAnEditThatCameAfterTheJudging(t *testing.T) {
	edits := WorkerEditsIn(t.TempDir())
	edits.KeepIn(7, "/checkout", "judged.py")
	edits.KeepIn(7, "/checkout", "later.py")

	edits.Approve(7, []string{"judged.py"})

	if said := edits.Of(7); !slices.Equal(said, []string{"later.py"}) {
		t.Fatalf("after approving the list is %v", said)
	}
}

func TestAnApprovedAgentStaysAnEditorWithNothingLeft(t *testing.T) {
	edits := WorkerEditsIn(t.TempDir())
	edits.KeepIn(7, "/checkout", "judged.py")

	edits.Approve(7, []string{"judged.py"})

	if len(edits.Of(7)) != 0 || len(edits.EditorsIn("/checkout")) != 1 {
		t.Fatalf("list %v, editors %v", edits.Of(7), edits.EditorsIn("/checkout"))
	}
}

func TestOnlyAnAgentsChangesReadTheEdits(t *testing.T) {
	data := t.TempDir()
	t.Setenv(pluginData, data)
	checkout := absolutely("", t.TempDir())
	WorkerEditsIn(data).KeepIn(7, checkout, "kept.py")

	for _, asked := range []warm.Request{
		{Judge: []string{"--changes"}, In: checkout},
		{Judge: []string{filepath.Join(checkout, "src")}, In: checkout, Agent: true},
	} {
		if edits := editsAsked(asked, checkout).scope(); edits.Held {
			t.Fatalf("a run %+v read the edits", asked)
		}
	}

	if edits := editsAsked(warm.Request{Judge: []string{"--changes"}, In: checkout, Agent: true}, checkout).scope(); !edits.Held {
		t.Fatal("an agent asking for its changes did not read the edits")
	}
}

func TestAPathEditedTwiceIsKeptOnce(t *testing.T) {
	data := t.TempDir()
	edits := WorkerEditsIn(data)

	edits.KeepIn(7, "/checkout", "src/twice.py")
	edits.KeepIn(7, "/checkout", "src/twice.py")

	raw, err := os.ReadFile(filepath.Join(data, workerEditsFolder, "7"))
	if err != nil || string(raw) != "src/twice.py\n" {
		t.Fatalf("the list on disk is %q: %v", raw, err)
	}
}

func askedForItsChangesIn(t *testing.T, checkout string, edited ...string) (WorkerEdits, EditsAsked) {
	t.Helper()

	data := t.TempDir()
	t.Setenv(pluginData, data)

	edits := WorkerEditsIn(data)
	for _, relative := range edited {
		edits.KeepIn(7, checkout, relative)
	}

	return edits, editsAsked(warm.Request{Judge: []string{"--changes"}, In: checkout, Agent: true}, checkout)
}

func TestACommittedFileLeavesTheEditsOfTheProjectsOwnCheckout(t *testing.T) {
	workerBranchOf(t, map[string]string{"src/committed.py": "one = 1\n"}, map[string]string{"src/work.py": "four = 4\n"})
	project := os.Getenv("JOURNAL_ROOT")
	wrote(t, project, map[string]string{"src/committed.py": "one = 2\n", "src/open.py": "two = 2\n", "src/removed.py": "three = 3\n"})

	edits, asked := askedForItsChangesIn(t, project, "src/committed.py", "src/open.py", "src/removed.py")

	gitIn(t, project, "add", "src/committed.py")
	gitIn(t, project, "commit", "-q", "-m", "one of the three edits")

	if err := os.Remove(filepath.Join(project, "src", "removed.py")); err != nil {
		t.Fatal(err)
	}

	if said := asked.scope().Files; !slices.Equal(said, []string{filepath.Join(project, "src", "open.py")}) {
		t.Errorf("the changes judged after the commit are %v", said)
	}

	if said := edits.Of(7); !slices.Equal(said, []string{"src/open.py"}) {
		t.Errorf("the list kept after the commit is %v", said)
	}
}

func TestAWorkersCommittedFileStaysInItsEditsUntilTheHandIn(t *testing.T) {
	worktree := workerBranchOf(t, map[string]string{"src/base.py": "one = 1\n"}, map[string]string{"src/work.py": "two = 2\n"})

	edits, asked := askedForItsChangesIn(t, worktree, "src/work.py")

	if said := asked.scope().Files; !slices.Equal(said, []string{filepath.Join(worktree, "src", "work.py")}) {
		t.Errorf("the worker's committed work is judged as %v", said)
	}

	if said := edits.Of(7); !slices.Equal(said, []string{"src/work.py"}) {
		t.Errorf("the worker's list after its commit is %v", said)
	}
}

func TestAPanicWhileJudgingClearsTheEdits(t *testing.T) {
	edits, asked := askedForItsChangesIn(t, absolutely("", t.TempDir()), "src/breaks.py")

	func() {
		defer func() {
			if recover() == nil {
				t.Error("the panic was swallowed, and the service has to end on it")
			}
		}()
		defer asked.clearedByAPanic()

		panic("the scan left the folders it was given")
	}()

	if said := edits.Of(7); len(said) != 0 {
		t.Errorf("the list that led to the panic is still %v", said)
	}
}
