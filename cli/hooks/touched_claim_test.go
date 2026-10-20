package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// TestAClaimLeavesWhatItDidNotTakeForTheNextLook holds the per-call check to every file a call wrote. Claiming
// advanced the mark to now before anything was judged, so of a script's burst of writes the newest few were
// judged and the rest were past the mark for ever: a later judge --changes found sins the check never raised.
// The mark now stays until nothing is left over, and the finding dedupe keeps each sin said once.
func TestAClaimLeavesWhatItDidNotTakeForTheNextLook(t *testing.T) {
	root := t.TempDir()
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	space := workspace.At(root, "claim-test")
	project := config.Config{Paths: []string{"src"}}
	touched := TouchedIn(space, root, project)

	touched.MarkSeen()
	time.Sleep(1100 * time.Millisecond)

	for _, name := range []string{"one.php", "two.php", "three.php"} {
		if err := os.WriteFile(filepath.Join(root, "src", name), []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	claimed, more := touched.Claim(2)
	if len(claimed) != 2 || !more {
		t.Fatalf("claimed %d of three files, more %v", len(claimed), more)
	}

	again, _ := touched.Claim(2)
	if len(again) != 2 {
		t.Fatalf("a claim that took two of three left %d for the next look, want the three still waiting", len(again))
	}

	// The mark is read at-or-after, since an mtime has one-second resolution, so a mark set in the same second as
	// the writes still counts them: the second has to pass before the mark can be past them.
	time.Sleep(1100 * time.Millisecond)
	touched.MarkSeen()

	// A fresh watcher, as each hook call builds one, so the mark is read from the file rather than from memory.
	if after, more := TouchedIn(space, root, project).Claim(2); len(after) != 0 || more {
		t.Errorf("after the look was marked, %d files are still claimed", len(after))
	}
}
