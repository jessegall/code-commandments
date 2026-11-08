package hooks

import (
	"os"
	"path/filepath"
	"strconv"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestEditsJudgedAtTheSameMomentAreAllCounted(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	authored := AuthoredIn(workspace.At(root, "s1"))
	changed := map[string]bool{}

	var edits sync.WaitGroup

	for n := range 24 {
		file := filepath.Join(root, strconv.Itoa(n)+".py")
		if err := os.WriteFile(file, nil, 0o644); err != nil {
			t.Fatal(err)
		}

		changed[file] = true

		edits.Add(1)

		go func() {
			defer edits.Done()
			authored.Wrote(root, file)
		}()
	}

	edits.Wait()

	if counted := len(authored.Among(changed)); counted != len(changed) {
		t.Errorf("%d files were edited and %d are counted", len(changed), counted)
	}
}
