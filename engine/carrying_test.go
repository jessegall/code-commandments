package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func treeOf(t *testing.T, paths ...string) *engine.Codebase {
	t.Helper()

	stream := &contract.Stream{Header: contract.Header{Language: contract.Python}}
	for _, path := range paths {
		stream.Files = append(stream.Files, &contract.File{Path: path, Language: contract.Python, Root: &contract.Node{}})
	}

	return engine.Load(stream)
}

type counted struct {
	built int
}

func TestAViewTakesItsBasesAnswerOnlyForAnAnalysisItDeclares(t *testing.T) {
	base := treeOf(t, "src/one.py", "src/two.py")

	held := &counted{}
	key := new(int)

	build := func(*engine.Codebase) *counted {
		held.built++

		return held
	}

	if engine.Analysis(base, key, build); held.built != 1 {
		t.Fatalf("the base built its analysis %d times", held.built)
	}

	carried := treeOf(t, "src/one.py", "src/two.py")
	carried.Carries(base, key)

	engine.Analysis(carried, key, build)

	if held.built != 1 {
		t.Errorf("a view that carries the analysis built it again: %d builds", held.built)
	}

	alone := treeOf(t, "src/one.py", "src/two.py")
	alone.Carries(base)

	engine.Analysis(alone, key, build)

	if held.built != 2 {
		t.Errorf("a view that does NOT name the analysis took the base's answer anyway: %d builds", held.built)
	}
}

func TestAnAnalysisIsCarriedOnlyWhenItsOwnSourcesAreUntouched(t *testing.T) {
	key := new(int)

	engine.Carrying(key, func(changed []string) bool {
		for _, path := range changed {
			if path == "config/app.py" {
				return false
			}
		}

		return true
	})

	if carried := engine.CarriedOver([]string{"src/one.py"}); len(carried) == 0 {
		t.Error("an analysis whose sources nothing touched is not carried, so a view rebuilds what it could have taken")
	}

	for _, carried := range engine.CarriedOver([]string{"config/app.py"}) {
		if carried == key {
			t.Error("an analysis is carried although the view changed a file it reads, so it would answer about text that is gone")
		}
	}
}
