// Package paritytest compares one engine's Go detectors with the findings the PHP tool recorded on a real project
// before it was removed: the check the port was done by, kept as a regression check against those recordings.
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

// Compare runs the engines' detectors over the project $COMMANDMENTS_PARITY names and fails for every finding only
// they or the PHP tool's recording, the file $COMMANDMENTS_PARITY_FINDINGS names (gzipped when it ends in .gz), makes.
// It skips the test without a project: a real project is the parity check, not the suite.
// $COMMANDMENTS_PARITY_EACH compares a solution project by project, one held at a time, so a solution too large to
// hold whole is still compared. $COMMANDMENTS_PARITY_ACCOUNTED names a file of the one-sided findings whose cause is
// known, which then pass. The Go half asks one bridge, kept running for every part, so its start-up and what it loads
// are paid once.
func Compare(t *testing.T, rules []catalog.Engine, command func(testing.TB, ...string) []string) {
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
	server, err := bridge.Serve(command(t, root))
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	found := map[string]bool{}
	for _, part := range parts {
		for _, finding := range goFindings(t, rules, server, root, part) {
			found[finding] = true
		}
		runtime.GC()
		debug.FreeOSMemory()
	}
	expected := map[string]bool{}
	for _, line := range strings.Split(strings.TrimSpace(string(php)), "\n") {
		if line != "" && !strings.HasPrefix(line, "#") {
			expected[line] = true
		}
	}
	explained := accounted(t)
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

// explanations are the one-sided findings $COMMANDMENTS_PARITY_ACCOUNTED lists, each with its cause after a `#`: a
// difference whose cause is known and written down passes, and one listed that no longer happens fails.
type explanations map[string]bool

// accounted reads the explanations the environment names; none when it names no file.
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

// pass says whether the one-sided finding is accounted for, and marks it met.
func (e explanations) pass(finding string) bool {
	if _, listed := e[finding]; !listed {
		return false
	}
	e[finding] = true

	return true
}

// unused are the explanations no finding met.
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

// goFindings is every finding the engine's Go detectors make in the part, read by the server, each as `path:line Sin`
// under the root.
func goFindings(t *testing.T, rules []catalog.Engine, server *bridge.Server, root, part string) []string {
	t.Helper()
	stream, err := server.Ask(bridge.Request{Paths: []string{part}})
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
		for _, finding := range detector.Find(codebase) {
			if file := strings.TrimPrefix(finding.File(), root+"/"); walked(file) {
				found = append(found, file+":"+strconv.Itoa(finding.Line())+" "+sin)
			}
		}
	}

	return found
}

// projectFolders is the folder of every project under the root, a project inside another's folder read as part of
// it, and never one in a hidden folder or in build output.
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

// sorted is the set's members in order.
func sorted(set map[string]bool) []string {
	members := make([]string, 0, len(set))
	for member := range set {
		members = append(members, member)
	}
	slices.Sort(members)

	return members
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

// walked says whether the walk that picks the files a judge reads lets the file through: never one in a hidden
// folder, which holds tooling, not source. The PHP tool hands its bridge only the files its walk lets through,
// and judge's walk leaves the same out.
func walked(file string) bool {
	return !slices.ContainsFunc(strings.Split(file, "/"), func(part string) bool { return strings.HasPrefix(part, ".") })
}
