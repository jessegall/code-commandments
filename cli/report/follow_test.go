package report

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/reported"
)

func TestAProjectWithNoReportsNeverAsksGitHub(t *testing.T) {
	project(t, "src/refund.py", "    return order.total or 0\n")
	asked := refuseToAsk(t)

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code != 0 {
		t.Errorf("a project with no reports answered %d", code)
	}

	if *asked {
		t.Error("GitHub was asked about a project that has filed no reports")
	}

	if !strings.Contains(said.String(), "nothing to follow") {
		t.Errorf("the run said %q", said)
	}
}

func TestAReportStillOpenAndUnansweredIsNothingToSay(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)
	ghViews(t, `{"state":"OPEN","title":"[detector-report] X","comments":[]}`)

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code != 0 {
		t.Errorf("an open report with no answer answered %d: %s", code, said)
	}

	if !strings.Contains(said.String(), "still stands as it was filed") {
		t.Errorf("the run said %q", said)
	}
}

func TestAFixedReportNamesTheReleaseAndWhatToDoAboutIt(t *testing.T) {
	for name, test := range map[string]struct {
		running string
		want    string
	}{
		"behind the release":  {"v6.9.0", "upgrade to v6.10.2"},
		"already past it":     {"v6.11.0", "already runs"},
		"a development build": {"dev", "development build"},
	} {
		t.Run(name, func(t *testing.T) {
			root := project(t, "src/refund.py", "    return order.total or 0\n")
			record(t, root, 814)
			ghViews(t, `{"state":"CLOSED","stateReason":"COMPLETED","title":"[detector-report] X","comments":[{"author":{"login":"jessegall"},"body":"Agreed, and fixed in v6.10.2."}]}`)

			said := &strings.Builder{}
			if code := (Command{Version: test.running}).follow(cli.Console{Out: said, Err: said}); code == 0 {
				t.Errorf("a report answered with a fix answered %d, and the project has something to do", code)
			}

			if !strings.Contains(said.String(), test.want) {
				t.Errorf("the run does not say %q:\n%s", test.want, said)
			}

			if !strings.Contains(said.String(), "report forget 814") {
				t.Errorf("the run does not say how to settle the row:\n%s", said)
			}
		})
	}
}

func TestAReportClosedWithNoReleaseSaysTheFindingStands(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)
	ghViews(t, `{"state":"CLOSED","stateReason":"NOT_PLANNED","title":"[detector-report] X","comments":[{"author":{"login":"jessegall"},"body":"The finding stands: the count had to be nullable for the rule to fire."}]}`)

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code == 0 {
		t.Error("a refused report answered success, and its sin is the project's to fix")
	}

	if !strings.Contains(said.String(), "the finding stands") {
		t.Errorf("the run does not say the finding stands:\n%s", said)
	}

	if strings.Contains(said.String(), "upgrade") {
		t.Errorf("the run says to upgrade for a report that was refused:\n%s", said)
	}
}

func TestAMaintainersQuestionIsHandedToTheAgentOnce(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)
	ghViews(t, `{"state":"OPEN","title":"[detector-report] X","comments":[{"author":{"login":"jessegall"},"body":"Which of the two shapes is yours? Paste the method."}]}`)

	first := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: first, Err: first}); code == 0 {
		t.Error("a question from the maintainer was not handed over")
	}

	if !strings.Contains(first.String(), "has said something") {
		t.Errorf("the run does not hand the question over:\n%s", first)
	}

	again := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: again, Err: again}); code != 0 {
		t.Errorf("the same question was raised a second time:\n%s", again)
	}

	held, _ := reported.Of(root)
	if seen := held.Rows()[0].Seen; seen.Comments != 1 || seen.State != "OPEN" {
		t.Errorf("the row remembers %+v of the look", seen)
	}
}

func TestGitHubThatCannotBeAskedIsAPassWithANotice(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)

	found := looking
	looking = func() (string, error) { return "", os.ErrNotExist }
	t.Cleanup(func() { looking = found })

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code != 0 {
		t.Errorf("a run that could not ask GitHub answered %d", code)
	}

	if !strings.Contains(said.String(), "issues/814") {
		t.Errorf("the run does not name the report to read by hand:\n%s", said)
	}
}

func TestWhatMovedIsWrittenWhereTheCheckReadsIt(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)
	ghViews(t, `{"state":"CLOSED","title":"[detector-report] X","comments":[{"author":{"login":"jessegall"},"body":"fixed in v6.10.2"}]}`)

	path := filepath.Join(t.TempDir(), "report.json")
	t.Setenv(cli.JournalReport, path)

	said := &strings.Builder{}
	(Command{Version: "v6.9.0"}).follow(cli.Console{Out: said, Err: said})

	raw, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}

	var written checkReport
	if err := json.Unmarshal(raw, &written); err != nil {
		t.Fatal(err)
	}

	if written.Title != "Reports" || len(written.Findings) != 1 {
		t.Fatalf("the check's report reads %+v", written)
	}

	if written.Findings[0].Name != "#814" || !strings.Contains(written.Findings[0].Where, "issues/814") {
		t.Errorf("the finding reads %+v", written.Findings[0])
	}
}

func TestForgettingAReportMakesItsFindingASinAgain(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)

	said := &strings.Builder{}
	if code := (Command{}).forget(cli.InputOf("report", "forget", "814"), cli.Console{Out: said, Err: said}); code != 0 {
		t.Errorf("forgetting a recorded report answered %d: %s", code, said)
	}

	held, _ := reported.Of(root)
	if len(held.Rows()) != 0 {
		t.Errorf("the record still holds %d rows", len(held.Rows()))
	}

	missing := &strings.Builder{}
	if code := (Command{}).forget(cli.InputOf("report", "forget", "900"), cli.Console{Out: missing, Err: missing}); code == 0 {
		t.Error("forgetting an issue no row holds answered success")
	}
}

func TestARowGitHubDoesNotKnowIsNamedAsStale(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)

	found, viewed := looking, viewing
	looking = func() (string, error) { return "gh", nil }
	viewing = func(string, string, int) (string, error) {
		return "", &unanswered{said: "GraphQL: Could not resolve to an Issue with the number of 814."}
	}
	t.Cleanup(func() { looking, viewing = found, viewed })

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code == 0 {
		t.Error("a row GitHub does not know answered success")
	}

	if !strings.Contains(said.String(), "not an issue GitHub knows") {
		t.Errorf("the run does not name the stale row:\n%s", said)
	}
}

func TestAnIssueTheRecordNeverKnewIsNamedButNeverAdopted(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)
	ghViews(t, `{"state":"OPEN","title":"[detector-report] X","comments":[]}`)
	ghLists(t, `[{"number":900,"title":"[detector-report] FeatureEnvyDetector","url":"https://github.com/jessegall/code-commandments/issues/900"},
		{"number":901,"title":"Something else entirely","url":"https://github.com/jessegall/code-commandments/issues/901"}]`)

	said := &strings.Builder{}
	if code := (Command{Version: "v6.10.2"}).follow(cli.Console{Out: said, Err: said}); code == 0 {
		t.Error("an issue no row knows answered success")
	}

	if !strings.Contains(said.String(), "#900") || !strings.Contains(said.String(), "no row of the record knows it") {
		t.Errorf("the run does not name the unrecorded issue:\n%s", said)
	}

	if strings.Contains(said.String(), "#901") {
		t.Errorf("the run names an issue this tool never filed:\n%s", said)
	}

	held, _ := reported.Of(root)
	if len(held.Rows()) != 1 {
		t.Errorf("an unrecorded issue was adopted into the record: %d rows", len(held.Rows()))
	}
}

func ghLists(t *testing.T, issues string) {
	t.Helper()
	listed := listing
	listing = func(string, string) (string, error) { return issues, nil }
	t.Cleanup(func() { listing = listed })
}

func TestAnAcceptedReportIsNeverAskedAboutAgain(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	record(t, root, 814)

	said := &strings.Builder{}
	code := (Command{}).accept(cli.InputOf("report", "accept", "814", "--why=pinned to v6.9 until the next quarter"), cli.Console{Out: said, Err: said})

	if code != 0 {
		t.Fatalf("accepting the answer answered %d: %s", code, said)
	}

	held, _ := reported.Of(root)
	if len(held.Rows()) != 1 || held.Rows()[0].Accepted == "" {
		t.Fatalf("the row reads %+v", held.Rows())
	}

	asked := refuseToAsk(t)
	followed := &strings.Builder{}
	if code := (Command{Version: "v6.9.0"}).follow(cli.Console{Out: followed, Err: followed}); code != 0 {
		t.Errorf("a run with nothing but an accepted report answered %d: %s", code, followed)
	}

	if *asked {
		t.Error("GitHub was asked about a report the project has accepted")
	}

	missing := &strings.Builder{}
	if code := (Command{}).accept(cli.InputOf("report", "accept", "900", "--why=anything"), cli.Console{Out: missing, Err: missing}); code == 0 {
		t.Error("accepting an issue no row holds answered success")
	}
}

func record(t *testing.T, root string, issue int) {
	t.Helper()
	row := reported.Row{
		Detector:   "InventedDefaultDetector",
		File:       filepath.Join(root, "src/refund.py"),
		Line:       1,
		Reason:     "zero is the true count of an empty set",
		Repository: "jessegall/code-commandments",
		Issue:      issue,
		Filed:      "2026-10-25T13:41:12Z",
	}

	if _, err := reported.Add(root, row); err != nil {
		t.Fatal(err)
	}
}

func ghViews(t *testing.T, state string) {
	t.Helper()
	found, viewed, listed := looking, viewing, listing
	looking = func() (string, error) { return "gh", nil }
	viewing = func(string, string, int) (string, error) { return state, nil }
	listing = func(string, string) (string, error) { return "[]", nil }
	t.Cleanup(func() { looking, viewing, listing = found, viewed, listed })
}

func refuseToAsk(t *testing.T) *bool {
	t.Helper()
	asked := false
	found, viewed := looking, viewing
	looking = func() (string, error) { return "gh", nil }
	viewing = func(string, string, int) (string, error) {
		asked = true

		return "", os.ErrClosed
	}
	t.Cleanup(func() { looking, viewing = found, viewed })

	return &asked
}
