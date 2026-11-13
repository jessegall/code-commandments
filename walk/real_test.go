package walk

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// A path is resolved once a process, through its links; a path not there yet is asked again once it is.
func TestAPathIsResolvedOnceAProcessThroughItsLinks(t *testing.T) {
	root := Real(t.TempDir())
	if err := os.Mkdir(filepath.Join(root, "real"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink(filepath.Join(root, "real"), filepath.Join(root, "linked")); err != nil {
		t.Fatal(err)
	}
	file := filepath.Join(root, "linked", "cart.py")
	if err := os.WriteFile(file, nil, 0o644); err != nil {
		t.Fatal(err)
	}

	if got, want := Real(file), filepath.Join(root, "real", "cart.py"); got != want {
		t.Fatalf("a file under a link resolved to %s, not %s", got, want)
	}
	if err := os.Remove(filepath.Join(root, "linked")); err != nil {
		t.Fatal(err)
	}
	if got, want := Real(file), filepath.Join(root, "real", "cart.py"); got != want {
		t.Errorf("a file resolved before was resolved again: %s, not the %s held from the first time", got, want)
	}

	later := filepath.Join(root, "real", "prices.py")
	if _, err := Resolved(later); err == nil {
		t.Fatalf("a file not there yet resolved")
	}
	if err := os.WriteFile(later, nil, 0o644); err != nil {
		t.Fatal(err)
	}
	if got, err := Resolved(later); err != nil || got != later {
		t.Errorf("a file written after a failed resolve resolved to %q, %v: the failure was held", got, err)
	}
}

// The walker is the one funnel that resolves a path: no other file of the module resolves links on its own, so a path is
// never resolved twice in two ways.
func TestOnlyTheWalkerResolvesLinks(t *testing.T) {
	module, err := filepath.Abs("..")
	if err != nil {
		t.Fatal(err)
	}
	funnel, _ := filepath.Abs("real.go")
	written := func(path string) bool {
		return strings.HasSuffix(path, ".go") && !strings.HasSuffix(path, "_test.go") && path != funnel &&
			!strings.HasSuffix(filepath.Dir(path), "test")
	}
	for _, path := range Files(module, Under(module, []string{"bin"}), written) {
		if text, err := os.ReadFile(path); err == nil && strings.Contains(string(text), "filepath.EvalSymlinks(") {
			t.Errorf("%s resolves links on its own: ask walk.Real or walk.Resolved", path)
		}
	}
}
