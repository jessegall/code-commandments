package judge

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func journalDriven(t *testing.T) string {
	t.Helper()

	project := emptyProject(t)
	manifest := filepath.Join(project, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	return project
}

func TestARunWhoseJournalRunsThePluginRefusesToReadTheTreeWhenNoServiceAnswers(t *testing.T) {
	code, said, warned := judgedIn(t, journalDriven(t), ".", "--no-checklist")

	if code != coldReadRefused {
		t.Errorf("exits %d, want %d", code, coldReadRefused)
	}
	if !strings.Contains(warned, "--allow-cold-read") || !strings.Contains(warned, "journal services start") {
		t.Errorf("it says %q, which names neither how to start the service nor how to read anyway", warned)
	}
	if said != "" {
		t.Errorf("it judged although it refused: %q", said)
	}
}

func TestARunAllowedToReadColdReadsTheTreeEvenWhereTheJournalRunsThePlugin(t *testing.T) {
	code, _, warned := judgedIn(t, journalDriven(t), ".", "--no-checklist", "--allow-cold-read")

	if code == coldReadRefused {
		t.Errorf("a run given --allow-cold-read refused: %s", warned)
	}
	if !strings.Contains(warned, "reads the whole tree itself") {
		t.Errorf("it says %q, which does not say it read the whole tree", warned)
	}
}

func TestARunInAProjectWithNoJournalReadsTheTreeAsBefore(t *testing.T) {
	code, _, warned := judgedIn(t, emptyProject(t), ".", "--no-checklist")

	if code == coldReadRefused {
		t.Errorf("a project with no journal, as in CI, refused to judge: %s", warned)
	}
}
