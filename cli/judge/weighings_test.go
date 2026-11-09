package judge

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAWeighingMovedToAnotherFolderReportsThatFolder(t *testing.T) {
	kept := weighing{Folder: "/one", Findings: []engine.Finding{
		{File: "/one/a.cs", Location: "/one/a.cs:3", Twins: []string{"/one/b.cs:9"}},
	}}

	moved, ok := kept.movedTo("/two")

	if !ok {
		t.Fatal("the findings could not be moved")
	}

	found := moved[0]
	if found.File != "/two/a.cs" || found.Location != "/two/a.cs:3" || found.Twins[0] != "/two/b.cs:9" {
		t.Fatalf("the finding still names the old folder: %+v", found)
	}

	if kept.Findings[0].Twins[0] != "/one/b.cs:9" {
		t.Fatalf("moving the findings changed what is kept: %+v", kept.Findings[0])
	}
}

func TestAWeighingOutsideItsFolderIsNotMoved(t *testing.T) {
	kept := weighing{Folder: "/one", Findings: []engine.Finding{{File: "/elsewhere/a.cs"}}}

	if _, ok := kept.movedTo("/two"); ok {
		t.Fatal("a finding outside its folder was answered from another folder")
	}
}

func TestAWeighingInItsOwnFolderIsAnsweredAsItIs(t *testing.T) {
	kept := weighing{Folder: "/one", Findings: []engine.Finding{{File: "/elsewhere/a.cs"}}}

	moved, ok := kept.movedTo("/one")

	if !ok || moved[0].File != "/elsewhere/a.cs" {
		t.Fatalf("the findings changed in their own folder: %+v", moved)
	}
}

func TestASetOutsideACheckoutIsNotRemembered(t *testing.T) {
	if newCSharpSet("").whole {
		t.Fatal("a set outside any checkout has no folder to move from, so it must not be remembered")
	}
}
