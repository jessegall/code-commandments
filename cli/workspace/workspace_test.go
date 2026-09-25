package workspace

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestASessionKeyIsTheStartOfItsIdsHashAsPhpCutsIt(t *testing.T) {
	if got := KeyFor("parity-session"); got != "0a010" {
		t.Errorf("KeyFor = %q", got)
	}
}

func TestANamedSessionsFolderIsItsNameInEveryWorktree(t *testing.T) {
	main := t.TempDir()
	gitIn(t, main, "init", "-q")
	gitIn(t, main, "commit", "-q", "--allow-empty", "-m", "start")
	linked := filepath.Join(t.TempDir(), "linked")
	gitIn(t, main, "worktree", "add", "-q", linked)

	if ok, err := At(linked, "abc").Names().Name("abc", "review"); !ok || err != nil {
		t.Fatalf("name: %v %v", ok, err)
	}

	for _, root := range []string{main, linked} {
		if key := At(root, "abc").SessionKey(); key != "review" {
			t.Errorf("%s: key %q", root, key)
		}
	}

	if key := At(main, "other").SessionKey(); key != KeyFor("other") {
		t.Errorf("unnamed key %q", key)
	}

	if taken, _ := At(main, "other").Names().Name("other", "review"); taken {
		t.Error("a name another session holds was given away")
	}
}

func TestNoSessionIsTheDefaultFolder(t *testing.T) {
	t.Setenv("CLAUDE_CODE_SESSION_ID", "")
	root := t.TempDir()

	if dir := At(root, "").SessionDir(); dir != root+"/.commandments/sessions/default" {
		t.Errorf("dir %q", dir)
	}
}

func TestTheJournalPluginMovesTheSessionFolders(t *testing.T) {
	root := t.TempDir()
	os.MkdirAll(root+"/.journal/plugins/code-commandments/.journal-plugin", 0o755)
	os.WriteFile(root+"/.journal/plugins/code-commandments/.journal-plugin/plugin.json", []byte("{}"), 0o644)
	os.MkdirAll(root+"/.commandments/sessions/abcde", 0o755)

	workspace := At(root, "x")

	if moved := workspace.RelocateSessions(); moved != 1 {
		t.Errorf("moved %d", moved)
	}

	if _, err := os.Stat(root + "/.journal/plugin-data/code-commandments/sessions/abcde"); err != nil {
		t.Error(err)
	}
}

func gitIn(t *testing.T, dir string, args ...string) {
	t.Helper()

	command := exec.Command("git", append([]string{"-C", dir, "-c", "user.email=t@t", "-c", "user.name=t"}, args...)...)

	if out, err := command.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v\n%s", args, err, out)
	}
}
