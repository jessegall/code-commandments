package dashboard

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
)

func TestTheDashboardListsWhatThisProjectHasReported(t *testing.T) {
	root := t.TempDir()
	write(t, root, "src/a.py", "    return order.total or 0\n")
	write(t, root, "src/b.py", "    return order.tax or 0\n")

	add(t, root, "InventedDefaultDetector", filepath.Join(root, "src/a.py"), 1, 814, reported.Seen{})
	add(t, root, "DictBagDetector", filepath.Join(root, "src/b.py"), 1, 900, reported.Seen{State: "CLOSED", Comments: 2, At: "2026-10-25T14:00:00Z"})

	drawn := rendered(t, root)
	page, held := drawn["pages"].(map[string]any)[reportsPage].(map[string]any)
	if !held {
		t.Fatalf("the dashboard has no reports page: %v", drawn["pages"])
	}

	if drawn["start"] != "overview" {
		t.Errorf("the dashboard starts on %v, and a reader must land on the overview", drawn["start"])
	}

	text := said(t, page)
	for _, want := range []string{"InventedDefaultDetector", "src/a.py:1", "#814", "not looked at yet", "CLOSED", "2 comment(s)"} {
		if !strings.Contains(text, want) {
			t.Errorf("the reports page does not name %q:\n%s", want, text)
		}
	}

	if waiting, closed := strings.Index(text, "#814"), strings.Index(text, "#900"); waiting > closed {
		t.Error("the report still waiting for an answer is listed below the one that is settled")
	}

	if !strings.Contains(said(t, drawn["pages"].(map[string]any)["overview"].(map[string]any)), reportsPage) {
		t.Error("the overview does not lead to the reports page")
	}
}

func TestAProjectThatReportedNothingIsToldNothing(t *testing.T) {
	root := t.TempDir()

	drawn := rendered(t, root)
	if _, held := drawn["pages"].(map[string]any)[reportsPage]; held {
		t.Error("a project with no reports has a reports page")
	}

	if strings.Contains(said(t, drawn["pages"].(map[string]any)["overview"].(map[string]any)), "Reported") {
		t.Error("the overview names reports in a project that has filed none")
	}
}

func TestARecordNobodyCanReadDrawsTheDashboardAnyway(t *testing.T) {
	root := t.TempDir()
	if err := os.MkdirAll(filepath.Dir(reported.Path(root)), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(reported.Path(root), []byte("{half a record"), 0o644); err != nil {
		t.Fatal(err)
	}

	drawn := rendered(t, root)
	if _, held := drawn["pages"].(map[string]any)["overview"]; !held {
		t.Error("the dashboard lost its overview over an unreadable record")
	}
}

func rendered(t *testing.T, root string) map[string]any {
	t.Helper()
	raw, err := json.Marshal(Render(nil, root))
	if err != nil {
		t.Fatal(err)
	}

	var drawn map[string]any
	if err := json.Unmarshal(raw, &drawn); err != nil {
		t.Fatal(err)
	}

	return drawn
}

func said(t *testing.T, page map[string]any) string {
	t.Helper()
	raw, err := json.Marshal(page)
	if err != nil {
		t.Fatal(err)
	}

	return string(raw)
}

func add(t *testing.T, root, detector, file string, line, issue int, seen reported.Seen) {
	t.Helper()
	row := reported.Row{
		Detector:   detector,
		File:       file,
		Line:       line,
		Reason:     "the reason",
		Repository: "jessegall/code-commandments",
		Issue:      issue,
		Filed:      "2026-10-2" + string(rune('0'+issue%10)) + "T10:00:00Z",
		Seen:       seen,
	}

	if _, err := reported.Add(root, row); err != nil {
		t.Fatal(err)
	}
}

func write(t *testing.T, root, path, text string) {
	t.Helper()
	full := filepath.Join(root, path)
	if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(full, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}
