package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestARefillReadsOnlyTheFoldersTheProjectDeclares(t *testing.T) {
	root, _ := workerProject(t)

	judged := judgesIn(root, root)

	for _, told := range []struct {
		what string
		path string
		read bool
	}{
		{"a file in a declared folder", filepath.Join(root, "src", "order.py"), true},
		{"a file in a folder nobody declared", filepath.Join(root, "docs", "notes.py"), false},
		{"a library folder nobody declared", filepath.Join(root, "node_modules", "left-pad", "index.ts"), false},
		{"a vendored dependency", filepath.Join(root, "vendor", "someone", "src", "Thing.php"), false},
		{"a file of a kind nobody judges", filepath.Join(root, "src", "notes.md"), false},
	} {
		if judged(told.path) != told.read {
			t.Errorf("%s is read=%v, and it should be %v", told.what, judged(told.path), told.read)
		}
	}
}

func TestALinkedLibraryFolderIsNeverFollowed(t *testing.T) {
	root, _ := workerProject(t)

	library := t.TempDir()
	if err := os.WriteFile(filepath.Join(library, "big.py"), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	linked := filepath.Join(root, "node_modules")
	if err := os.Symlink(library, linked); err != nil {
		t.Skipf("this filesystem has no links: %v", err)
	}

	judged := judgesIn(root, root)

	if judged(filepath.Join(linked, "big.py")) {
		t.Error("a file inside a linked library folder is read, so a worker with a linked node_modules pays for somebody else's code")
	}

	if !judged(filepath.Join(root, "src", "order.py")) {
		t.Error("the project's own declared folder stopped being read")
	}
}

func TestWithNoConfigEverythingIsReadRatherThanNothing(t *testing.T) {
	root := t.TempDir()

	judged := judgesIn(root, root)

	if !judged(filepath.Join(root, "src", "order.py")) {
		t.Error("with no config at all a refill reads nothing, which is worse than reading too much: it would answer about a tree it never read")
	}
}

func TestTheDeclaredFoldersAreTheWorktreesOwn(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 27)

	cutInto(t, root, worktree, "feature/declared")

	judged := judgesIn(root, worktree)

	if !judged(filepath.Join(worktree, "src", "order.py")) {
		t.Error("the worker's own declared folder is not read, so its refill reads nothing of its work")
	}

	if judged(filepath.Join(root, "src", "order.py")) && !strings.HasPrefix(root, worktree) {
		t.Error("the project's own copy is read as part of the worker's refill, which is the main checkout's text in a worker's answer")
	}
}
