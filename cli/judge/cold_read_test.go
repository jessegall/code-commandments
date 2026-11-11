package judge

import (
	"os"
	"path/filepath"
	"testing"
)

func TestARunWhereAJournalIsJudgesTheTreeItselfWithNoService(t *testing.T) {
	t.Setenv("JOURNAL_PROJECT_ROOT", "/somewhere")

	project := emptyProject(t)
	worktree := filepath.Join(project, ".journal", "worktrees", "development", "worker-1")
	if err := os.MkdirAll(worktree, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(worktree, "cart.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, folder := range []string{project, worktree} {
		if code, _, warned := judgedIn(t, folder, ".", "--no-checklist"); code != 0 {
			t.Errorf("a run in %s, where a journal is and no service answers, exits %d instead of judging: %s", folder, code, warned)
		}
	}
}
