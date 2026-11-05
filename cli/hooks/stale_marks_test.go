package hooks

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)


// TestAMarkedFileTheTreeVouchesForIsNotReadAgain holds what a stop is allowed to cost. A stop and a heartbeat
// sweep every file that still carries a mark, so a sin that is gone turns green even where nothing edited that
// file again — and in a measured run of nine edits that sweep re-read seven files, found 28 marks and settled
// nothing at all, once at every stop and every heartbeat of every open agent.
//
// A mark's sin can only be GONE if its file has changed, and every edit is judged as it lands, so a file the
// tree knows stands as it read it cannot have lost the sin it carries. Where no tree is held nothing is known
// and every marked file is read, as it always was.
func TestAMarkedFileTheTreeVouchesForIsNotReadAgain(t *testing.T) {
	root, _ := migrationProject(t)
	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	vouched := filepath.Join(root, "shop", "migrations", "m004_drop_fades.py")
	moved := filepath.Join(root, "shop", "migration.py")

	// With no tree held, nothing is known and both are read.
	kept.Drop(project, branch)

	if read := stillToRead(root, []string{vouched, moved}); len(read) != 2 {
		t.Errorf("with no tree held the sweep reads %d of 2 marked files; it knows nothing, so it must read both", len(read))
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	// One of them has moved since the tree read it, and the other has not.
	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(moved, []byte("class Migration:\n    number = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	read := stillToRead(root, []string{vouched, moved})

	if len(read) != 1 || read[0] != moved {
		t.Errorf("the sweep reads %v; it owes a read to the file that moved and nothing to the one the tree vouches for", read)
	}
}
