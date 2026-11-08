package git_test

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func journalCut(t *testing.T) (project, worktree string) {
	t.Helper()

	project, err := filepath.EvalSymlinks(repository(t))
	if err != nil {
		t.Fatal(err)
	}

	worktree = filepath.Join(project, ".journal", "worktrees", "development", "worker-1")
	run(t, project, "worktree", "add", "-q", "-b", "worker-1", worktree)

	return project, worktree
}

func TestAWorktreeTheJournalNamesIsAnsweredFromWhatTheJournalSaid(t *testing.T) {
	project, worktree := journalCut(t)

	t.Setenv("JOURNAL_CHECKOUT_ROOT", worktree)
	t.Setenv("JOURNAL_PROJECT_ROOT", project)
	t.Setenv("JOURNAL_BASE_COMMIT", "the-commit-the-journal-recorded")

	if root := git.ProjectRoot(filepath.Join(worktree, "a.go")); root != project {
		t.Errorf("the project root is %q, want the journal's %q", root, project)
	}

	if start := git.WorkStart(worktree); start != "the-commit-the-journal-recorded" {
		t.Errorf("the work starts at %q, which is not the commit the journal recorded the cut at", start)
	}
}

func TestTheProjectTheJournalNamesStartsItsWorkAtHead(t *testing.T) {
	project, _ := journalCut(t)

	t.Setenv("JOURNAL_CHECKOUT_ROOT", project)
	t.Setenv("JOURNAL_PROJECT_ROOT", project)
	t.Setenv("JOURNAL_BASE_COMMIT", "")

	if start := git.WorkStart(project); start != "HEAD" {
		t.Errorf("the project's own work starts at %q, want %s", start, "HEAD")
	}
}

func TestAFolderOutsideTheCheckoutTheJournalNamesIsAskedOfGit(t *testing.T) {
	project, worktree := journalCut(t)

	t.Setenv("JOURNAL_CHECKOUT_ROOT", project)
	t.Setenv("JOURNAL_PROJECT_ROOT", project)
	t.Setenv("JOURNAL_BASE_COMMIT", "the-commit-the-journal-recorded")

	if start := git.WorkStart(worktree); start == "HEAD" || start == "the-commit-the-journal-recorded" {
		t.Errorf("a worktree the variables do not describe started its work at %q, not at its own cut", start)
	}

	if root := git.ProjectRoot(worktree); root != project {
		t.Errorf("git names the worktree's project as %q, want %q", root, project)
	}
}
