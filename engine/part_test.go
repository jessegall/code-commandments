package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func TestALanguagesPartKeepsItsAnalysesBetweenTwoReaders(t *testing.T) {
	tree := treeOf(t, "src/one.py", "src/two.py")

	built := 0
	key := new(int)
	build := func(*engine.Codebase) int {
		built++

		return built
	}

	engine.Analysis(tree.Of(contract.Python), key, build)
	engine.Analysis(tree.Of(contract.Python), key, build)

	if built != 1 {
		t.Errorf("the Python part built its analysis %d times for two readers of the same tree", built)
	}
}

func TestAPartReportsOnlyWhatTheCodebaseItCameFromReports(t *testing.T) {
	tree := sample(t)
	every := func(engine.Match) bool { return true }

	whole := tree.Of(contract.PHP).Where(every).Count()
	narrowed := tree.Reporting(tree.Files()[0].Path).Of(contract.PHP).Where(every).Count()
	again := tree.Of(contract.PHP).Where(every).Count()

	if whole == 0 || narrowed == 0 || narrowed >= whole {
		t.Fatalf("the whole part holds %d nodes and the part of a codebase narrowed to one of its %d files holds %d", whole, len(tree.Files()), narrowed)
	}
	if again != whole {
		t.Errorf("after a narrowed reader the whole part holds %d nodes, and it held %d", again, whole)
	}
}

func TestAPartHoldsOnlyItsOwnLanguage(t *testing.T) {
	tree := treeOf(t, "src/one.py")

	if held := len(tree.Of(contract.PHP).Files()); held != 0 {
		t.Errorf("the PHP part of a Python tree holds %d files", held)
	}
	if held := len(tree.Of(contract.Python).Files()); held != 1 {
		t.Errorf("the Python part of a tree of one Python file holds %d files", held)
	}
}

func TestAnAnalysisFirstAskedByANarrowedReaderHoldsEveryFile(t *testing.T) {
	tree := treeOf(t, "src/one.py", "src/two.py")
	key := struct{ name string }{"files a whole-program analysis saw"}
	count := func(c *engine.Codebase) int { return c.WhereKind("file").Count() }

	first := engine.Analysis(tree.Reporting(tree.Files()[0].Path), key, count)
	second := engine.Analysis(tree.Reporting(tree.Files()[1].Path), key, count)

	if first != 2 || second != 2 {
		t.Errorf("an analysis asked by readers narrowed to one file each saw %d and %d of the 2 files", first, second)
	}
}
