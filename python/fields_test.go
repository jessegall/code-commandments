package python_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

func TestFieldClumpsAndMasksAreFoundWhereThePHPEngineFindsThem(t *testing.T) {
	codebase := shopFixture(t)
	var want struct {
		Coupled map[string]bool `json:"coupled"`
		Masked  []string        `json:"masked"`
	}
	golden(t, "fields", &want)
	for _, match := range codebase.WhereClass().Get() {
		class := python.Node{Match: match}
		key := match.Node().Symbol + "@" + place(class)
		if codebase.Program.IsCoupled(class) != want.Coupled[key] {
			t.Errorf("%s is coupled: %v", key, !want.Coupled[key])
		}
	}
	var masked []string
	for _, match := range codebase.WhereExpression().Get() {
		if (python.Node{Match: match}).MasksOwnState() {
			masked = append(masked, strings.TrimPrefix(match.File(), fixture.root+"/")+"@"+itoa(match.Node().Span.Start)+"-"+itoa(match.Node().Span.End))
		}
	}
	slices.Sort(masked)
	if strings.Join(masked, ",") != strings.Join(want.Masked, ",") {
		t.Errorf("masks %v, not %v", masked, want.Masked)
	}
}
