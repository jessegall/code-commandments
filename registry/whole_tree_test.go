package registry_test

import (
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
)

// TestTheDetectorsThatReadBeyondOneFileAreThePHPToolsOwn holds the WholeTree marks to the PHP tool's own
// analysis of which detectors read beyond the file they judge (CrossFileSet), for every engine the binary
// carries: the per-edit check asks only the rest.
func TestTheDetectorsThatReadBeyondOneFileAreThePHPToolsOwn(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("no php to ask the PHP tool's analysis")
	}

	repo, _ := filepath.Abs("..")
	script := `require '` + repo + `/vendor/autoload.php';
$set = \JesseGall\CodeCommandments\Detectors\CrossFileSet::over(\JesseGall\CodeCommandments\Ast\Codebase::scan(['` + repo + `/src']));
foreach (\JesseGall\CodeCommandments\Detectors\Catalog::all() as $detector) {
    if ($set->has($detector)) { echo get_class($detector), "\n"; }
}`

	out, err := exec.Command("php", "-d", "memory_limit=2G", "-r", script).Output()
	if err != nil {
		t.Fatalf("php: %v", err)
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
		if _, wholeTree := detector.(detectors.WholeTree); wholeTree {
			got = append(got, config.ClassOf(config.Detector, detector))
		}
	}

	slices.Sort(want)
	slices.Sort(got)

	if !slices.Equal(got, want) {
		t.Errorf("marked\n%s\nthe PHP tool's analysis finds\n%s", strings.Join(got, "\n"), strings.Join(want, "\n"))
	}
}
