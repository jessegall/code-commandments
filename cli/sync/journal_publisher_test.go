package sync

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/library"
)

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

func TestSyncLeavesWhatTheJournalPublishedSinceItStoodAside(t *testing.T) {
	root := phpProject(t)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	installPlugin(t, root)

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	index := filepath.Join(root, ".claude/skills", "commandments", "SKILL.md")

	for _, skill := range []string{filepath.Join(root, library.Dir, "commandments-php-absence"),
		filepath.Join(root, ".claude/skills", "commandments-php-absence"), filepath.Dir(index)} {
		if err := os.MkdirAll(skill, 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(skill, "SKILL.md"), []byte("---\nname: "+filepath.Base(skill)+"\n---\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	if err := Sync(root, quiet()); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(index); err != nil {
		t.Errorf("a later sync took away the index the journal published for Claude Code: %v", err)
	}

	for _, folder := range []string{library.Dir, ".claude/skills"} {
		if left := publishedIn(t, filepath.Join(root, folder)); left != 1 {
			t.Errorf("a later sync left %d of the one skill the journal published into %s", left, folder)
		}
	}
}

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

func quiet() cli.Console {
	return cli.Console{Out: &bytes.Buffer{}, Err: &bytes.Buffer{}}
}

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
