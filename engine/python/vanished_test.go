package python_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine"
)

// TestAFileThatIsGoneLeavesTheRestRead holds the Python bridge to a file it cannot read. mypy lists a folder and
// the bridge then asks the filesystem about each file it listed, so a file deleted between the two, a worktree
// removed under a run, ended the stream in the middle and lost every other file with it: "the stream ends after
// line 1976 without a trailer". A file that is gone is no part of the project the request asks about.
func TestAFileThatIsGoneLeavesTheRestRead(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "cart.py"), []byte("def total(prices: list[int]) -> int:\n    return sum(prices)\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink(filepath.Join(root, "nothing-here.py"), filepath.Join(root, "gone.py")); err != nil {
		t.Fatal(err)
	}

	stream, err := bridge.Once(bridgetest.Mypy(t), root)
	if err != nil {
		t.Fatalf("a file that is gone took the whole read with it: %v", err)
	}

	codebase := engine.Load(stream)
	read := map[string]bool{}
	for _, file := range codebase.Files() {
		read[filepath.Base(file.Path)] = true
	}
	if !read["cart.py"] {
		t.Errorf("the files read are %v, and the one that is there is not among them", read)
	}
}
