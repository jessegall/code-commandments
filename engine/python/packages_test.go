package python_test

import (
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestThePackageGraphDrawsTheArrowsThePHPEngineDraws(t *testing.T) {
	codebase := shopFixture(t)
	var want struct {
		Arrows  []string `json:"arrows"`
		Closing []string `json:"closing"`
	}
	golden(t, "packages", &want)
	relative := func(folder string) string { return strings.TrimPrefix(folder, fixture.root+string(filepath.Separator)) }
	var arrows, closing []string
	for _, arrow := range codebase.Program.PackageArrows() {
		arrows = append(arrows, place(python.Node{Match: arrow.At})+" "+relative(arrow.From)+" -> "+relative(arrow.To))
	}
	for _, at := range codebase.Program.PackageArrows().ClosingAMutualPair() {
		closing = append(closing, place(python.Node{Match: at}))
	}
	for _, list := range [][]string{arrows, closing, want.Arrows, want.Closing} {
		slices.Sort(list)
	}
	if strings.Join(arrows, "\n") != strings.Join(want.Arrows, "\n") {
		t.Errorf("the arrows are\n%s\nnot\n%s", strings.Join(arrows, "\n"), strings.Join(want.Arrows, "\n"))
	}
	if strings.Join(closing, ",") != strings.Join(want.Closing, ",") {
		t.Errorf("the closing arrows are %v, not %v", closing, want.Closing)
	}
}

// TestATestModuleDrawsNoArrowBetweenPackages holds a test to what it is: a feature's test that drives another
// package end to end reaches across on purpose, so its import closes no cycle, while a module of the package itself
// importing back still does.
func TestATestModuleDrawsNoArrowBetweenPackages(t *testing.T) {
	sources := map[string]string{
		"pytest.ini":                "[pytest]\npython_files = test.py\n",
		"app/__init__.py":           "",
		"app/commands/__init__.py":  "",
		"app/commands/demo.py":      "from app.recording.store import Store\n\ndef demo() -> Store:\n    return Store()\n",
		"app/recording/__init__.py": "",
		"app/recording/store.py":    "class Store:\n    pass\n",
		"app/recording/test.py":     "from app.commands.demo import demo\n\ndef test_demo() -> None:\n    assert demo()\n",
	}
	if closing := pythontest.FromSource(t, sources); len(python.In(closing).Program.PackageArrows().ClosingAMutualPair()) != 0 {
		t.Errorf("a test module's import closed a cycle")
	}

	sources["app/recording/replay.py"] = "from app.commands.demo import demo\n\ndef replay() -> None:\n    demo()\n"
	if closing := pythontest.FromSource(t, sources); len(python.In(closing).Program.PackageArrows().ClosingAMutualPair()) == 0 {
		t.Errorf("a module of the package importing back closed no cycle")
	}
}
