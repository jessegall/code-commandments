package hooks

import (
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/detectors"
)

// TestAReportedSinIsNotToldToTheAgent holds what a report means at the moment a file is written: the agent is not
// sent to fix a finding whose claim a maintainer is answering, and the sin is not named at it again.
func TestAReportedSinIsNotToldToTheAgent(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	fileReport(t, root, "RedundantElseDetector", file, 2)

	Judge4Into(editOf("src/refund.py"), data)

	for _, queued := range drained(t, data) {
		if strings.Contains(asText(queued["tell"]), "redundant-python-else") {
			t.Errorf("the agent was told to fix a sin this project has reported: %v", queued["tell"])
		}
	}
}

// TestASinNobodyReportedIsStillToldToTheAgent holds the other half, so the one above cannot pass by silencing
// everything: the same sin, with no report filed, reaches the agent as it always did.
func TestASinNobodyReportedIsStillToldToTheAgent(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	Judge4Into(editOf("src/refund.py"), data)

	told := false
	for _, queued := range drained(t, data) {
		told = told || strings.Contains(asText(queued["tell"]), "redundant-python-else")
	}

	if !told {
		t.Error("a sin nobody reported was not told to the agent")
	}
}

// TestAMarkOfAReportedSinIsStillMade holds the thing that must NOT simply disappear. The chat marks and the sins
// dashboard are settled against the marks a look made, so a reported sin left out of them would settle as a sin
// that was fixed, which is a lie about work nobody has done.
func TestAMarkOfAReportedSinIsStillMade(t *testing.T) {
	root, file, _ := projectWithASin(t)
	fileReport(t, root, "RedundantElseDetector", file, 2)

	marks := marksOfOneFile(t, root, file)
	if len(marks) == 0 {
		t.Fatal("no mark was made for a reported sin")
	}

	filed := 0
	for _, mark := range marks {
		if mark.IsFiled() {
			filed++

			if mark.Filed.Issue != 814 {
				t.Errorf("the mark names issue %d", mark.Filed.Issue)
			}
		}
	}

	if filed == 0 {
		t.Error("no mark knows its sin is reported, so nothing downstream can tell the difference")
	}
}

// TestAWorkersMergeIsNotHeldForAReportedSin holds the merge gate to the same rule: holding a branch over a claim a
// maintainer is answering would hold it for as long as the answer takes.
func TestAWorkersMergeIsNotHeldForAReportedSin(t *testing.T) {
	root, file, _ := projectWithASin(t)
	fileReport(t, root, "RedundantElseDetector", file, 2)

	if held := MergeHeld(mergingOf(root, generousWindow, "src/refund.py")); held != "" {
		t.Errorf("a merge was held for a sin this project has reported: %q", held)
	}

	if _, dropped, err := reported.Forget(root, 814); err != nil || !dropped {
		t.Fatalf("forgetting the report answered %v, %v", dropped, err)
	}

	if held := MergeHeld(mergingOf(root, generousWindow, "src/refund.py")); held == "" {
		t.Error("the merge is let through once the report is forgotten, and the sin is the project's again")
	}
}

// editOf is the line the journal sends for a file a tool changed.
func editOf(path string) map[string]any {
	return map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
		"data": map[string]any{"path": path, "kind": "edit", "added": 1, "removed": 0, "tool_use": 41},
	}
}

// marksOfOneFile is every mark a look at the file makes, as the hooks make them.
func marksOfOneFile(t *testing.T, root, file string) []SinMark {
	t.Helper()
	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}

	return marksIn(Marks(root), file, slices.DeleteFunc(enabled, detectors.ReadsBeyondOneFile), git.ChangedLines{})
}

// fileReport records a report about the rule's finding at this line of the file.
func fileReport(t *testing.T, root, detector, file string, line int) {
	t.Helper()
	row := reported.Row{
		Detector:   detector,
		File:       file,
		Line:       line,
		Reason:     "the else is the clearer half",
		Repository: "jessegall/code-commandments",
		Issue:      814,
		Filed:      "2026-10-25T13:41:12Z",
	}

	if _, err := reported.Add(root, row); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(reported.Path(root)); err != nil {
		t.Fatalf("the record was not written: %v", err)
	}
}
