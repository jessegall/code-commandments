package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestAWorkerWhoseBaseIsAdvancedKeepsNoStaleTextOfItsOwn(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 15)

	cutInto(t, root, worktree, "feature/advanced")

	under := filepath.Join("src", "made.py")
	file := filepath.Join(worktree, under)
	wroteInto(t, file, sinOnLineTwo)

	settings, _ := config.Load(root)

	kept.CutAt(worktree, "aaaaaaa")

	if said := linesToldFor(t, worktree, file, settings); !strings.Contains(said, ":2") {
		t.Fatalf("the worker's own text is answered as %q, and its sin is on line 2", said)
	}

	if held := kept.Over(worktree).Count(); held == 0 {
		t.Fatal("the lane holds none of the worker's files, so there is no stale text for the advance to clear")
	}

	wroteInto(t, file, sinOnLineFive)

	kept.CutAt(worktree, "bbbbbbb")

	if held := kept.Over(worktree).Count(); held != 0 {
		t.Errorf("the lane kept %d file(s) of the worker across the advance, which is the stale text the next answer would carry", held)
	}

	said := linesToldFor(t, worktree, file, settings)

	if strings.Contains(said, ":2") {
		t.Errorf("after the advance the answer still names the line the old text had: %s", said)
	}

	if !strings.Contains(said, ":6") {
		t.Errorf("after the advance the answer is %q, and the worker's text has its sin on line 6", said)
	}
}

func TestAFileThatMatchesTheNewBaseIsNoLongerTheWorkersOwn(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 16)

	cutInto(t, root, worktree, "feature/advanced-match")

	under := filepath.Join("src", "made.py")
	file := filepath.Join(worktree, under)
	theProjects := readFileOr(t, filepath.Join(root, under))

	wroteInto(t, file, sinOnLineTwo)

	settings, _ := config.Load(root)
	kept.CutAt(worktree, "aaaaaaa")

	linesToldFor(t, worktree, file, settings)

	wroteInto(t, file, theProjects)

	kept.CutAt(worktree, "bbbbbbb")

	if held := kept.Over(worktree).Count(); held != 0 {
		t.Fatalf("the lane still holds %d file(s) after the advance", held)
	}

	if said := linesToldFor(t, worktree, file, settings); strings.Contains(said, ":2") {
		t.Errorf("a file whose text now matches the project is still answered from the worker's old copy: %s", said)
	}
}

func readFileOr(t *testing.T, file string) string {
	t.Helper()

	said, err := os.ReadFile(file)
	if err != nil {
		t.Fatal(err)
	}

	return string(said)
}
