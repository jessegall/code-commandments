package python_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// TestTheGoDetectorsFindWhatThePHPOnesFind runs both engines' Python detectors over the project
// $COMMANDMENTS_PARITY names and fails for every finding only one of them makes. It is skipped without one: a real
// project is the parity check, not the suite.
func TestTheGoDetectorsFindWhatThePHPOnesFind(t *testing.T) {
	project := os.Getenv("COMMANDMENTS_PARITY")
	if project == "" {
		t.Skip("set COMMANDMENTS_PARITY to a Python project to compare the engines on")
	}
	root, err := filepath.EvalSymlinks(project)
	if err != nil {
		t.Fatal(err)
	}
	php, err := exec.Command("php", "../../engine/python/testdata/findings.php", root).Output()
	if err != nil {
		t.Fatalf("the PHP engine failed: %v", err)
	}
	stream, err := bridge.Once(bridge.TestMypy(t), root)
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.Load(stream)
	var found []string
	for _, detector := range detectors.Of(catalog.Python) {
		sin := strings.TrimSuffix(catalog.Name(detector), "Detector")
		for _, finding := range detector.Find(codebase) {
			if at := strings.TrimPrefix(finding.File(), root+"/") + ":" + strconv.Itoa(finding.Line()) + " " + sin; !slices.Contains(found, at) {
				found = append(found, at)
			}
		}
	}
	slices.Sort(found)
	expected := strings.Fields(strings.ReplaceAll(strings.TrimSpace(string(php)), " ", "\x00"))
	for at, line := range expected {
		expected[at] = strings.ReplaceAll(line, "\x00", " ")
	}
	for _, finding := range expected {
		if !slices.Contains(found, finding) {
			t.Errorf("only PHP: %s", finding)
		}
	}
	for _, finding := range found {
		if !slices.Contains(expected, finding) {
			t.Errorf("only Go:  %s", finding)
		}
	}
	t.Logf("%d findings in PHP, %d in Go", len(expected), len(found))
}
