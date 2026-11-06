package scan

import (
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

func TestAJudgingInAWorktreeReachesTheProjectsBridge(t *testing.T) {
	project := repository(t)
	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inRepository(t, project, "worktree", "add", "-b", "a-worker", worktree)

	listening, err := net.Listen("unix", bridge.ServiceSocket("php", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listening.Close()

	t.Setenv("JOURNAL_ROOT", project)
	t.Setenv("CLAUDE_PROJECT_DIR", "")
	chdir(t, worktree)

	edited := filepath.Join(worktree, "src", "a.php")
	if _, kept := keptFor("php", []string{filepath.Dir(edited)}); !kept {
		t.Error("a judging in a worktree finds no bridge, so it starts one of its own and pays its start-up on every edit")
	}
}

func TestAJudgingInAWorktreeFindsTheProjectWithoutTheJournal(t *testing.T) {
	project := repository(t)
	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inRepository(t, project, "worktree", "add", "-b", "a-worker", worktree)

	listening, err := net.Listen("unix", bridge.ServiceSocket("php", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listening.Close()

	t.Setenv("JOURNAL_ROOT", "")
	t.Setenv("CLAUDE_PROJECT_DIR", "")
	chdir(t, worktree)

	if _, kept := keptFor("php", []string{filepath.Join(worktree, "src")}); !kept {
		t.Error("outside the journal, a judging in a worktree finds no bridge")
	}
}

func TestAJudgingOutsideTheProjectReachesNoBridge(t *testing.T) {
	project := repository(t)

	listening, err := net.Listen("unix", bridge.ServiceSocket("php", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listening.Close()

	t.Setenv("JOURNAL_ROOT", project)
	t.Setenv("CLAUDE_PROJECT_DIR", "")
	chdir(t, project)

	elsewhere, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	if _, kept := keptFor("php", []string{elsewhere}); kept {
		t.Error("a folder outside the project is answered by the project's bridge")
	}
}

func repository(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	inRepository(t, root, "init", "-b", "main")
	inRepository(t, root, "config", "user.email", "test@example.com")
	inRepository(t, root, "config", "user.name", "A Test")

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src", "a.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	inRepository(t, root, "add", "-A")
	inRepository(t, root, "commit", "-m", "first")

	return root
}

func inRepository(t *testing.T, root string, args ...string) {
	t.Helper()

	command := exec.Command("git", args...)
	command.Dir = root
	if out, err := command.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v\n%s", args, err, out)
	}
}
