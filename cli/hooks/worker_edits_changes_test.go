package hooks

import (
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
