package python_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestATestModuleIsNoPartOfTheLayerItTests holds the layer rule to production code: a feature's own test, named by
// the project's pytest python_files, may reach any layer, while the feature itself may not (#627).
func TestATestModuleIsNoPartOfTheLayerItTests(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"pytest.ini":           "[pytest]\npython_files = test.py\n",
		"features/__init__.py": "",
		"features/ask/__init__.py": "",
		"features/ask/ask.py":  "from commands.http import dispatch\n\n\ndef ask() -> str:\n    return dispatch('ask')\n",
		"features/ask/test.py": "from commands.http import dispatch\n\n\ndef test_ask() -> None:\n    assert dispatch('ask') == 'ask'\n",
		"commands/__init__.py": "",
		"commands/http.py":     "def dispatch(name: str) -> str:\n    return name\n",
		"resources/__init__.py": "",
	})
	detector := python.NamespaceDependencyDetector{}.Layer("features", "resources").Layer("commands").Layer("resources")

	found := detector.Find(codebase)

	if len(found) != 1 || !strings.HasSuffix(found[0].File(), "ask/ask.py") {
		var at []string
		for _, each := range found {
			at = append(at, each.Location())
		}
		t.Errorf("the layer rule flags %v, not the feature's own module alone", at)
	}
}
