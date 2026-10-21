package judge

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestTheJournalReportListsEachFindingWithItsFileLineAndSkill(t *testing.T) {
	findings := []engine.Finding{
		{Detector: "DictBagDetector", Skill: "python/value-objects", Sin: "python-dict-bag", Location: "src/x.py:12", Scope: "call"},
		{Detector: "ComponentBudgetDetector", Skill: "vue/vue-components", Sin: "oversized-component", Location: "src/Page.vue", Scope: "<section>"},
	}
	var got journalReport
	if err := json.Unmarshal(NewReport(".", findings, nil, nil, Skipped{}).Journal(), &got); err != nil {
		t.Fatal(err)
	}
	if got.Title != "Sins" || got.Summary != "2 sins across 2 skills" || len(got.Findings) != 2 {
		t.Fatalf("the report is titled, summed up and lists every finding: %+v", got)
	}
	byName := map[string]journalFinding{}
	for _, finding := range got.Findings {
		byName[finding.Name] = finding
	}
	if bag := byName["python-dict-bag"]; bag.File != "src/x.py" || bag.Line != 12 || bag.Group != "python/value-objects" || bag.Where != "call" {
		t.Fatalf("a finding names its file, its line, where in them and the skill it is grouped under: %+v", bag)
	}
	if page := byName["oversized-component"]; page.File != "src/Page.vue" || page.Line != 0 {
		t.Fatalf("a finding with no line names its file alone: %+v", page)
	}
}
