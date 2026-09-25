package scan

import (
	"os/exec"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheScanFindsWhatTheFrontendParityToolFinds(t *testing.T) {
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("the frontend bridge needs node")
	}

	root := "../../tests/Fixtures/frontend"
	sources := Walk([]string{root}, source.Excluded{})

	if sources.Count(source.Vue, source.TypeScript) == 0 {
		t.Fatal("no frontend sources walked")
	}

	codebase, err := sources.Load()
	if err != nil {
		t.Fatal(err)
	}

	var found []string

	for _, detector := range append(detectors.Of(catalog.Frontend), detectors.Of(catalog.TypeScript)...) {
		for _, match := range detector.Find(codebase) {
			found = append(found, match.Location()+" ["+catalog.Name(detector)+"]")
		}
	}

	slices.Sort(found)
	found = slices.Compact(found)

	out, err := exec.Command("go", "run", "../../engine/frontend/parity", root).Output()
	if err != nil {
		t.Fatal(err)
	}

	if want := strings.Split(strings.TrimSpace(string(out)), "\n"); len(found) != len(want) {
		t.Errorf("scan found %d, the parity tool %d", len(found), len(want))
	}
}
