// Package paritytest compares one engine's Go detectors with the PHP ones on a real project: the check a port is
// done by.
package paritytest

import (
	"bytes"
	"compress/gzip"
	"io"
	"io/fs"
	"os"
	"os/exec"
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

// Compare runs the engine's detectors in both tools over the project $COMMANDMENTS_PARITY names, the PHP ones through
// the findings script, and fails for every finding only one of them makes. It skips the test without a project: a
// real project is the parity check, not the suite. $COMMANDMENTS_PARITY_FINDINGS names a file the PHP findings are
// kept in, read back on the next run, since the PHP half of a large project takes the longest.
// $COMMANDMENTS_PARITY_EACH compares a solution project by project, one held at a time, so a solution too large to
// hold whole is still compared; each tool then reads each project alone. The Go half asks one bridge, kept running for
// every part, so its start-up and what it loads are paid once.
func Compare(t *testing.T, rules catalog.Engine, findings string, command func(testing.TB, ...string) []string) {
	t.Helper()
	project := os.Getenv("COMMANDMENTS_PARITY")
	if project == "" {
		t.Skipf("set COMMANDMENTS_PARITY to a %s project to compare the engines on", rules.Label())
	}
	root, err := filepath.EvalSymlinks(project)
	if err != nil {
		t.Fatal(err)
	}
	parts := []string{root}
	if os.Getenv("COMMANDMENTS_PARITY_EACH") != "" {
		parts = projectFolders(root)
	}
	php, err := phpFindings(findings, root, parts)
	if err != nil {
		t.Fatalf("the PHP engine failed: %v", err)
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
	for _, finding := range sorted(expected) {
		if !found[finding] {
			t.Errorf("only PHP: %s", finding)
		}
	}
	for _, finding := range sorted(found) {
		if !expected[finding] {
			t.Errorf("only Go:  %s", finding)
		}
	}
	t.Logf("%d findings in PHP, %d in Go, over %d part(s)", len(expected), len(found), len(parts))
}

// goFindings is every finding the engine's Go detectors make in the part, read by the server, each as `path:line Sin`
// under the root.
func goFindings(t *testing.T, rules catalog.Engine, server *bridge.Server, root, part string) []string {
	t.Helper()
	stream, err := server.Ask(bridge.Request{Paths: []string{part}})
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.Load(stream)
	var found []string
	for _, detector := range detectors.Of(rules) {
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

// phpFindings is what the PHP engine finds under root: kept in $COMMANDMENTS_PARITY_FINDINGS once found, gzipped
// when the name ends in .gz, and read back from there when it is. A line opening with `#` says where the findings
// came from and is not one of them.
func phpFindings(script, root string, parts []string) ([]byte, error) {
	kept := os.Getenv("COMMANDMENTS_PARITY_FINDINGS")
	if kept != "" {
		if found, err := readKept(kept); err == nil {
			return found, nil
		}
	}
	var found []byte
	for _, part := range parts {
		out, err := exec.Command("php", script, part).Output()
		if err != nil {
			return nil, err
		}
		prefix := strings.TrimPrefix(strings.TrimPrefix(part, root), "/")
		for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
			if line == "" {
				continue
			}
			if prefix != "" {
				line = prefix + "/" + line
			}
			found = append(found, line+"\n"...)
		}
	}
	if kept == "" {
		return found, nil
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
