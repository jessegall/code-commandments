package judge

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/engine"
)

// TestAReportedFindingIsSetAsideFromTheOnesStillToAnswerFor holds the split every surface of a run is built on:
// what the project has reported is told apart from what it still answers for, by the rule and the line's own text,
// so an edit above a reported line leaves it reported and a rewrite of the line itself does not.
func TestAReportedFindingIsSetAsideFromTheOnesStillToAnswerFor(t *testing.T) {
	root, file := projectWithARecord(t)

	active, filed, read := apart(root, []engine.Finding{
		finding("InventedDefaultDetector", file, 2),
		finding("RedundantElseDetector", file, 2),
		finding("InventedDefaultDetector", file, 1),
	})

	if !read {
		t.Fatal("the record does not read")
	}

	if len(filed) != 1 || filed[0].Row.Issue != 814 {
		t.Fatalf("the reported finding was not set aside: %+v", filed)
	}

	if len(active) != 2 {
		t.Fatalf("%d findings are still to answer for, want the other two", len(active))
	}

	for _, held := range active {
		if _, line := splitLocation(held.Location); held.Detector == "InventedDefaultDetector" && line == 2 {
			t.Error("the reported finding is still among the ones to answer for")
		}
	}
}

// TestARecordNobodyCanReadLeavesEveryFindingActive holds the safe way round: a record that no longer reads as one
// cannot quietly excuse a sin, so every finding stands and the run says the record is the thing to look at.
func TestARecordNobodyCanReadLeavesEveryFindingActive(t *testing.T) {
	root, file := projectWithARecord(t)
	if err := os.WriteFile(reported.Path(root), []byte("{half a record"), 0o644); err != nil {
		t.Fatal(err)
	}

	active, filed, read := apart(root, []engine.Finding{finding("InventedDefaultDetector", file, 2)})

	if read {
		t.Error("a record nobody can read says it read")
	}

	if len(active) != 1 || len(filed) != 0 {
		t.Errorf("%d active and %d reported, want every finding active", len(active), len(filed))
	}
}

// TestAProjectThatReportedNothingIsJudgedAsBefore holds the cost of the record on a project that files no reports:
// none, and the findings come back as they were given.
func TestAProjectThatReportedNothingIsJudgedAsBefore(t *testing.T) {
	root := t.TempDir()
	given := []engine.Finding{finding("InventedDefaultDetector", filepath.Join(root, "src", "refund.py"), 2)}

	active, filed, read := apart(root, given)

	if !read || len(filed) != 0 || len(active) != 1 {
		t.Errorf("a project with no record answered %d active, %d reported, read %v", len(active), len(filed), read)
	}
}

// TestTheJournalsReportCountsOnlyWhatIsStillToAnswerFor holds the gate the journal's check reads: a reported
// finding is not a failing finding, or a check would stay red for a claim a maintainer is already answering.
func TestTheJournalsReportCountsOnlyWhatIsStillToAnswerFor(t *testing.T) {
	root, file := projectWithARecord(t)
	active, filed, _ := apart(root, []engine.Finding{
		finding("InventedDefaultDetector", file, 2),
		finding("RedundantElseDetector", file, 1),
	})

	var read journalReport
	if err := json.Unmarshal(NewReport(root, active, nil, nil, Skipped{}).Reporting(filed).Journal(), &read); err != nil {
		t.Fatal(err)
	}

	if len(read.Findings) != 1 || !strings.HasPrefix(read.Summary, "1 sin") {
		t.Errorf("the journal's report holds %d findings and sums them as %q", len(read.Findings), read.Summary)
	}
}

// TestTheChecklistKeepsAReportedLineWhereNobodyWillFixIt holds the worklist's own split: the lines to work are
// above, the reported ones are in a section of their own that says not to fix them and not to delete them, and
// the count in the heading is the work.
func TestTheChecklistKeepsAReportedLineWhereNobodyWillFixIt(t *testing.T) {
	root, file := projectWithARecord(t)
	active, filed, _ := apart(root, []engine.Finding{
		finding("InventedDefaultDetector", file, 2),
		finding("RedundantElseDetector", file, 1),
	})

	checklist := NewReport(root, active, nil, nil, Skipped{}).Reporting(filed).Checklist()

	if !strings.HasPrefix(checklist, "# Code Commandments — 1 sins to fix") {
		t.Errorf("the checklist's heading counts the reported line as work: %q", strings.SplitN(checklist, "\n", 2)[0])
	}

	section, held := strings.Index(checklist, "## Reported — do not fix"), strings.Index(checklist, "issues/814")
	if section < 0 || held < 0 {
		t.Fatalf("the checklist has no reported section naming the issue:\n%s", checklist)
	}

	if work := strings.Index(checklist, "## "); work == section {
		t.Error("the reported section is the first section, so a reader meets it before the work")
	}

	if !strings.Contains(checklist, "Do NOT fix these and do NOT delete these lines") {
		t.Error("the reported section does not say to leave its lines alone")
	}
}

// TestTheConsoleNamesWhatIsReportedAndWhereToReadIt holds what a teammate who did not file the report sees: the
// line, the rule, the issue and the day, so the argument can be read rather than guessed at.
func TestTheConsoleNamesWhatIsReportedAndWhereToReadIt(t *testing.T) {
	root, file := projectWithARecord(t)
	active, filed, _ := apart(root, []engine.Finding{
		finding("InventedDefaultDetector", file, 2),
		finding("RedundantElseDetector", file, 1),
	})

	console := NewReport(root, active, nil, nil, Skipped{}).Reporting(filed).Console()

	if !strings.Contains(console, "1 finding reported and left out of the count") {
		t.Errorf("the run does not say what it set aside:\n%s", console)
	}

	if !strings.Contains(console, "issues/814") || !strings.Contains(console, "filed 2026-10-25") {
		t.Errorf("the run does not say where the report is or when it was filed:\n%s", console)
	}
}

// projectWithARecord is a project whose second line of src/refund.py is reported as InventedDefault, issue 814.
func projectWithARecord(t *testing.T) (root, file string) {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	file = filepath.Join(root, "src", "refund.py")
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte("def refund(order):\n    return order.total or 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	row := reported.Row{
		Detector:   "InventedDefaultDetector",
		Sin:        "csharp-invented-default",
		File:       file,
		Line:       2,
		Reason:     "zero is the true count of an empty set",
		Repository: "jessegall/code-commandments",
		Issue:      814,
		Filed:      "2026-10-25T13:41:12Z",
	}

	if _, err := reported.Add(root, row); err != nil {
		t.Fatal(err)
	}

	return root, file
}

// finding is one finding of a rule at a line of a file.
func finding(detector, file string, line int) engine.Finding {
	return engine.Finding{
		Detector: detector,
		Skill:    "backend/absence",
		Sin:      "invented-default",
		File:     file,
		Location: file + ":" + strconv.Itoa(line),
		Scope:    "MethodDeclaration refund",
	}
}
