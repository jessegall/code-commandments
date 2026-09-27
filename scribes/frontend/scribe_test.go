package frontend

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/scribes"
)

type everywhere struct{}

func (everywhere) Includes(string) bool { return true }
func (everywhere) IsScoped() bool       { return false }

// project writes files into a fresh folder and answers its real path.
func project(t *testing.T, files map[string]string) string {
	t.Helper()
	dir, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, content := range files {
		path := filepath.Join(dir, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return dir
}

// goRewrites is what the detector's step rewrites in the folder here, keyed by path relative to it.
func goRewrites(t *testing.T, detector detectors.Detector, dir string) map[string]string {
	t.Helper()
	scanner := served(t)
	steps := scribes.Steps(catalog.Frontend, scanner, []detectors.Detector{detector})
	if len(steps) != 1 {
		t.Fatalf("no step fixes %T", detector)
	}
	rewrites, err := steps[0].Run(scribes.Pass{Roots: []string{dir}, Scope: everywhere{}, Frozen: scribes.Frozens{scanner}})
	if err != nil {
		t.Fatal(err)
	}
	relative := map[string]string{}
	for path, content := range rewrites.Contents() {
		relative[strings.TrimPrefix(path, dir+"/")] = content
	}

	return relative
}

// sameAsPHP runs the detector's step over the files and holds its rewrite byte for byte equal to what PHP's step
// rewrote, as recorded; it answers the rewrite.
func sameAsPHP(t *testing.T, detector detectors.Detector, detectorClass string, files map[string]string) map[string]string {
	t.Helper()
	dir := project(t, files)
	want := phpAnswer(t, detectorClass, files)
	got := goRewrites(t, detector, dir)
	if len(got) != len(want) {
		t.Fatalf("rewrote %v, PHP %v", keys(got), keys(want))
	}
	for path, content := range want {
		if got[path] != content {
			t.Errorf("%s differs:\n--- go\n%s\n--- php\n%s", path, got[path], content)
		}
	}

	return got
}

func keys(files map[string]string) []string {
	var names []string
	for name := range files {
		names = append(names, name)
	}

	return names
}
