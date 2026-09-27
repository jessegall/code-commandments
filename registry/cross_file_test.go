package registry_test

import (
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
)

// TestTheDetectorsThatReadBeyondOneFileAreThePHPToolsOwn holds the detectors that read beyond one file to the PHP
// tool's own CrossFileSet, for every engine the binary carries: a WholeTree verdict, a recurrence grouped across files,
// or a CrossFile reach the analysis finds, recorded in testdata/cross-file.txt. The per-edit check asks only the rest.
func TestTheDetectorsThatReadBeyondOneFileAreThePHPToolsOwn(t *testing.T) {
	out, err := os.ReadFile("testdata/cross-file.txt")
	if err != nil {
		t.Fatal(err)
	}

	carried := map[catalog.Engine]bool{}
	for _, detector := range detectors.All() {
		engine, _ := detectors.EngineOf(detector)
		carried[engine] = true
	}

	var want, got []string

	for _, class := range strings.Fields(string(out)) {
		if rule, shipped := config.RuleOf(class); shipped && carried[rule.Engine] {
			want = append(want, class)
		}
	}

	for _, detector := range detectors.All() {
		if detectors.ReadsBeyondOneFile(detector) {
			got = append(got, config.ClassOf(config.Detector, detector))
		}
	}

	slices.Sort(want)
	slices.Sort(got)

	if !slices.Equal(got, want) {
		t.Errorf("marked\n%s\nthe PHP tool's analysis finds\n%s", strings.Join(got, "\n"), strings.Join(want, "\n"))
	}
}
