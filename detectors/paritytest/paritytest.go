// Package paritytest compares one engine's Go detectors with the findings the PHP tool recorded on a real project before it was removed: the check the port was done by, kept as a regression check against those recordings.
package paritytest

import (
	"compress/gzip"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"runtime"
	"runtime/debug"
	"slices"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

func Compare(t *testing.T, rules []catalog.Engine, command func(testing.TB, ...string) []string) {
	compare(t, rules, command, nil)
}

func CompareRecordedFixture(t *testing.T, rules []catalog.Engine, command func(testing.TB, ...string) []string, sources []string) {
	t.Helper()
	compare(t, rules, command, sources)
}

func compare(t *testing.T, rules []catalog.Engine, command func(testing.TB, ...string) []string, sources []string) {
	t.Helper()
	project := os.Getenv("COMMANDMENTS_PARITY")
	if project == "" {
		t.Skipf("set COMMANDMENTS_PARITY to a %s project to compare the engines on", rules[0].Label())
	}
	absolute, err := filepath.Abs(project)
	if err != nil {
		t.Fatal(err)
	}
	root, err := filepath.EvalSymlinks(absolute)
	if err != nil {
		t.Fatal(err)
	}
	parts := []string{root}
	if os.Getenv("COMMANDMENTS_PARITY_EACH") != "" {
		parts = projectFolders(root)
	}
	kept := os.Getenv("COMMANDMENTS_PARITY_FINDINGS")
	if kept == "" {
		t.Fatal("set COMMANDMENTS_PARITY_FINDINGS to the PHP tool's recorded findings on the project")
	}
	php, err := readKept(kept)
	if err != nil {
		t.Fatalf("the PHP tool's recorded findings do not read: %v", err)
	}
	expected := map[string]bool{}
	for _, line := range strings.Split(strings.TrimSpace(string(php)), "\n") {
		if line != "" && !strings.HasPrefix(line, "#") {
			expected[line] = true
		}
	}
	explained := accounted(t)
	var recordedRules map[string]bool
	if sources != nil {
		recordedRules = map[string]bool{}
		for finding := range expected {
			recordedRules[ruleName(finding)] = true
		}
		for finding := range explained {
			_, at, _ := strings.Cut(finding, ": ")
			file, _, _ := strings.Cut(at, ":")
			if !recordedRules[ruleName(finding)] || !slices.Contains(sources, file) {
				delete(explained, finding)
			}
		}
	}
	server, err := bridge.Serve(command(t, root))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	found := map[string]bool{}
	for _, part := range parts {
		var paths []string
		if sources == nil {
			paths = bridge.Listed(part)
		} else {
			paths = make([]string, 0, len(sources))
			for _, source := range sources {
				paths = append(paths, filepath.Join(part, source))
			}
		}
		for _, finding := range goFindings(t, rules, server, root, paths, recordedRules) {
			found[finding] = true
		}
		runtime.GC()
		debug.FreeOSMemory()
	}
	for _, finding := range sorted(expected) {
		if !found[finding] && !explained.pass("only PHP: "+finding) {
			t.Errorf("only PHP: %s", finding)
		}
	}
	for _, finding := range sorted(found) {
		if !expected[finding] && !explained.pass("only Go: "+finding) {
			t.Errorf("only Go:  %s", finding)
		}
	}
	for _, stale := range explained.unused() {
		t.Errorf("accounted for, but no longer found: %s", stale)
	}
	t.Logf("%d findings in PHP, %d in Go, over %d part(s)", len(expected), len(found), len(parts))
}

func ruleName(finding string) string {
	return finding[strings.LastIndexByte(finding, ' ')+1:]
}

// explanations are the one-sided findings $COMMANDMENTS_PARITY_ACCOUNTED lists, each with its cause after a `#`: a difference whose cause is known and written down passes, and one listed that no longer happens fails.
type explanations map[string]bool

func accounted(t *testing.T) explanations {
	t.Helper()
	listed := explanations{}
	path := os.Getenv("COMMANDMENTS_PARITY_ACCOUNTED")
	if path == "" {
		return listed
	}
	raw, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	for _, line := range strings.Split(string(raw), "\n") {
		if finding, _, _ := strings.Cut(line, "#"); strings.TrimSpace(finding) != "" && !strings.HasPrefix(line, "#") {
			listed[strings.TrimSpace(finding)] = false
		}
	}

	return listed
}

func (e explanations) pass(finding string) bool {
	if _, listed := e[finding]; !listed {
		return false
	}
	e[finding] = true

	return true
}

func (e explanations) unused() []string {
	var unused []string
	for finding, met := range e {
		if !met {
			unused = append(unused, finding)
		}
	}
	slices.Sort(unused)

	return unused
}

func goFindings(t *testing.T, rules []catalog.Engine, server *bridge.Server, root string, paths []string, recordedRules map[string]bool) []string {
	t.Helper()
	stream, err := server.Ask(bridge.Request{Paths: paths})
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.Load(stream)
	var found []string
	var proven []detectors.Detector
	for _, each := range rules {
		proven = append(proven, detectors.Of(each)...)
	}
	for _, detector := range proven {
		sin := strings.TrimSuffix(catalog.Name(detector), "Detector")
		if recordedRules != nil && !recordedRules[sin] {
			continue
		}
		for _, finding := range detector.Find(codebase) {
			if file := strings.TrimPrefix(finding.File(), root+"/"); walked(file) {
				found = append(found, file+":"+strconv.Itoa(finding.Line())+" "+sin)
			}
		}
	}

	return found
}

func projectFolders(root string) []string {
	var folders []string
	filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || !entry.IsDir() {
			return nil
		}
		if name := entry.Name(); path != root && (strings.HasPrefix(name, ".") || name == "bin" || name == "obj" || name == "node_modules") {
			return filepath.SkipDir
		}
		if projects, _ := filepath.Glob(filepath.Join(path, "*.csproj")); len(projects) > 0 {
			folders = append(folders, path)

			return filepath.SkipDir
		}

		return nil
	})

	return folders
}

func sorted(set map[string]bool) []string {
	members := make([]string, 0, len(set))
	for member := range set {
		members = append(members, member)
	}
	slices.Sort(members)

	return members
}

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

func walked(file string) bool {
	return !slices.ContainsFunc(strings.Split(file, "/"), func(part string) bool { return strings.HasPrefix(part, ".") })
}
