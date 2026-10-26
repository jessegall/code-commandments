package sync

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/library"
)

// TestSyncPublishesTheSkillsForAProjectWithoutAJournal holds the ordinary case, which is every project that has
// no journal: this side is the only publisher there is, so the library is written and each agent is pointed at it.
func TestSyncPublishesTheSkillsForAProjectWithoutAJournal(t *testing.T) {
	root := phpProject(t)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	if published := publishedIn(t, filepath.Join(root, library.Dir)); published == 0 {
		t.Fatal("a project with no journal gets no skills")
	}
	if linked := publishedIn(t, filepath.Join(root, ".claude/skills")); linked == 0 {
		t.Error("the skills are published but Claude Code is pointed at none of them")
	}
}

// TestSyncStandsAsideWhereTheJournalPublishes holds the other half of that rule. Installed as the journal's
// plugin, the journal copies the plugin's own rendered skills into the agents' folders and takes them away when
// the plugin goes; two publishers of one path would fight over it, so this side publishes nothing and withdraws
// what it published before, leaving the journal's files alone.
func TestSyncStandsAsideWhereTheJournalPublishes(t *testing.T) {
	root := phpProject(t)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}
	before := publishedIn(t, filepath.Join(root, library.Dir))
	if before == 0 {
		t.Fatal("the first sync published nothing, so there is nothing to withdraw")
	}

	installPlugin(t, root)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	if left := publishedIn(t, filepath.Join(root, library.Dir)); left != 0 {
		t.Errorf("%d skills of ours are still in the library the journal publishes into", left)
	}
	if linked := publishedIn(t, filepath.Join(root, ".claude/skills")); linked != 0 {
		t.Errorf("%d links of ours are still in Claude Code's folder", linked)
	}
}

// TestASkillTheProjectPlacedItselfSurvivesStandingAside holds the limit of that withdrawal: it takes away what
// this tool published and nothing else. A project may write a skill of its own into the library beside ours, and
// a publish is not entitled to it however it is named.
func TestASkillTheProjectPlacedItselfSurvivesStandingAside(t *testing.T) {
	root := phpProject(t)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	own := filepath.Join(root, library.Dir, "ours-alone")
	if err := os.MkdirAll(own, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(own, "SKILL.md"), []byte("---\nname: ours-alone\n---\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	installPlugin(t, root)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(own, "SKILL.md")); err != nil {
		t.Errorf("the project's own skill was taken away with ours: %v", err)
	}
}

// TestRemovingThePluginGivesThePublishingBack holds the way back. The journal takes its copies away with the
// plugin, and the next sync, which composer runs on install and update, is the only thing that can put skills
// there again.
func TestRemovingThePluginGivesThePublishingBack(t *testing.T) {
	root := phpProject(t)
	installPlugin(t, root)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}
	if left := publishedIn(t, filepath.Join(root, library.Dir)); left != 0 {
		t.Fatalf("%d skills are published while the journal publishes them", left)
	}

	if err := os.RemoveAll(filepath.Join(root, ".journal")); err != nil {
		t.Fatal(err)
	}

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	if published := publishedIn(t, filepath.Join(root, library.Dir)); published == 0 {
		t.Error("the plugin is gone and the skills came back nowhere")
	}
}

// quiet is a console nothing reads, for a test about what lands on disk rather than what is said.
func quiet() cli.Console {
	return cli.Console{Out: &bytes.Buffer{}, Err: &bytes.Buffer{}}
}

// phpProject is a project the sync has something to do: one language written, so the curriculum has skills to
// publish for it.
func phpProject(t *testing.T) string {
	t.Helper()
	root := t.TempDir()
	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".commandments", "config.json"), []byte(`{"paths": ["src"]}`), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// installPlugin writes the one file that says the tool is installed as the journal's plugin, which is what
// workspace.IsJournalDriven reads.
func installPlugin(t *testing.T, root string) {
	t.Helper()
	dir := filepath.Join(root, ".journal", "plugins", "code-commandments", ".journal-plugin")
	if err := os.MkdirAll(dir, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(dir, "plugin.json"), []byte(`{"name": "code-commandments"}`), 0o644); err != nil {
		t.Fatal(err)
	}
}

// publishedIn is how many skills of the curriculum sit in the folder, by the prefix every published id carries.
func publishedIn(t *testing.T, dir string) int {
	t.Helper()
	entries, err := os.ReadDir(dir)
	if err != nil {
		return 0
	}

	found := 0
	for _, entry := range entries {
		if len(entry.Name()) > len("commandments-") && entry.Name()[:len("commandments-")] == "commandments-" {
			found++
		}
	}

	return found
}
