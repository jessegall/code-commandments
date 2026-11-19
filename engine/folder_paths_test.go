package engine_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAPathIsCleanedOfDotsAndEmptySegments(t *testing.T) {
	cases := map[string][]string{
		"app/./Exports//daily":   {"app", "Exports", "daily"},
		"app/Exports/../Imports": {"app", "Imports"},
		"../stubs/model.stub":    {"..", "stubs", "model.stub"},
	}
	for path, want := range cases {
		if got := engine.CleanPath(engine.PathSegments(path)); !slices.Equal(got, want) {
			t.Errorf("%s cleaned to %v, want %v", path, got, want)
		}
	}
}
