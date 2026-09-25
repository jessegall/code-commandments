package python_test

import (
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/python"
)

func TestEveryDefReachesWhatThePHPEngineSays(t *testing.T) {
	codebase := shopFixture(t)
	var want map[string][]string
	golden(t, "reach", &want)
	population := codebase.Program.FunctionReach()
	for _, match := range codebase.WhereFunction().Get() {
		def := python.Node{Match: match}
		var resources []string
		for resource := range population.Of(python.DeclarationOf(def)) {
			resources = append(resources, resource)
		}
		slices.Sort(resources)
		if got, answer := strings.Join(resources, ","), strings.Join(want[place(def)], ","); got != answer {
			t.Errorf("%s reaches %s, not %s", place(def), got, answer)
		}
	}
}
