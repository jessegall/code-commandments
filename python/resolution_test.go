package python_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

func TestTheDefsThatUnpackAContainerAreThePHPEnginesOwn(t *testing.T) {
	codebase := shopFixture(t)
	var want []string
	golden(t, "resolution", &want)
	var unpacks []string
	for _, match := range codebase.WhereFunction().Get() {
		if def := (python.Node{Match: match}); codebase.Program.UnpacksTargetFromContainerParam(def) {
			unpacks = append(unpacks, match.Node().Symbol+"@"+place(def))
		}
	}
	slices.Sort(unpacks)
	if strings.Join(unpacks, ",") != strings.Join(want, ",") {
		t.Errorf("%v, not %v", unpacks, want)
	}
}
