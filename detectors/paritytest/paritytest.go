// Package paritytest compares one engine's Go detectors with the PHP ones on a real project: the check a port is
// done by.
package paritytest

import (
	"bytes"
	"compress/gzip"
	"io"
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

// Compare runs the engine's detectors in both tools over the project $COMMANDMENTS_PARITY names, the PHP ones through
// the findings script, and fails for every finding only one of them makes. It skips the test without a project: a
// real project is the parity check, not the suite. $COMMANDMENTS_PARITY_FINDINGS names a file the PHP findings are
// kept in, read back on the next run, since the PHP half of a large project takes the longest.
func Compare(t *testing.T, rules catalog.Engine, findings string, command func(testing.TB) []string) {
	t.Helper()
	project := os.Getenv("COMMANDMENTS_PARITY")
	if project == "" {
		t.Skipf("set COMMANDMENTS_PARITY to a %s project to compare the engines on", rules.Label())
	}
	root, err := filepath.EvalSymlinks(project)
	if err != nil {
		t.Fatal(err)
	}
	php, err := phpFindings(findings, root)
	if err != nil {
		t.Fatalf("the PHP engine failed: %v", err)
	}
	stream, err := bridge.Once(command(t), root)
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.Load(stream)
	var found []string
	for _, detector := range detectors.Of(rules) {
		sin := strings.TrimSuffix(catalog.Name(detector), "Detector")
		for _, finding := range detector.Find(codebase) {
			file := strings.TrimPrefix(finding.File(), root+"/")
			if !walked(file) {
				continue
			}
			if at := file + ":" + strconv.Itoa(finding.Line()) + " " + sin; !slices.Contains(found, at) {
				found = append(found, at)
			}
		}
	}
	slices.Sort(found)
	var expected []string
	for _, line := range strings.Split(strings.TrimSpace(string(php)), "\n") {
		if line != "" && !strings.HasPrefix(line, "#") {
			expected = append(expected, line)
		}
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

// phpFindings is what the PHP engine finds under root: kept in $COMMANDMENTS_PARITY_FINDINGS once found, gzipped
// when the name ends in .gz, and read back from there when it is. A line opening with `#` says where the findings
// came from and is not one of them.
func phpFindings(script, root string) ([]byte, error) {
	kept := os.Getenv("COMMANDMENTS_PARITY_FINDINGS")
	if kept != "" {
		if found, err := readKept(kept); err == nil {
			return found, nil
		}
	}
	found, err := exec.Command("php", script, root).Output()
	if err != nil || kept == "" {
		return found, err
	}

	return found, writeKept(kept, found)
}

// readKept is the findings kept in the file.
func readKept(path string) ([]byte, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer file.Close()
	if !strings.HasSuffix(path, ".gz") {
		return io.ReadAll(file)
	}
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		return nil, err
	}

	return io.ReadAll(unzipped)
}

// writeKept keeps the findings in the file.
func writeKept(path string, found []byte) error {
	if !strings.HasSuffix(path, ".gz") {
		return os.WriteFile(path, found, 0o644)
	}
	var zipped bytes.Buffer
	writer := gzip.NewWriter(&zipped)
	if _, err := writer.Write(found); err != nil {
		return err
	}
	if err := writer.Close(); err != nil {
		return err
	}

	return os.WriteFile(path, zipped.Bytes(), 0o644)
}

// walked says whether the walk that picks the files a judge reads lets the file through: never one in a hidden
// folder, which holds tooling, not source. The PHP tool hands its bridge only the files its walk lets through,
// and judge's walk leaves the same out.
func walked(file string) bool {
	return !slices.ContainsFunc(strings.Split(file, "/"), func(part string) bool { return strings.HasPrefix(part, ".") })
}
