package hooks

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func servingTheConfig(t *testing.T, project string) func(document string) {
	t.Helper()

	folder := t.TempDir()
	answer := filepath.Join(folder, "answer")
	journal := filepath.Join(folder, "journal")
	if err := os.WriteFile(journal, []byte("#!/bin/sh\ncat "+answer+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	t.Setenv("JOURNAL_BIN", journal)
	t.Setenv(journalRoot, project)

	return func(document string) {
		if err := os.WriteFile(answer, []byte(`{"version": 1, "config": `+document+`}`), 0o644); err != nil {
			t.Fatal(err)
		}
	}
}

func TestATreeIsReadAgainWhenTheJournalsConfigMovesTheFoldersAJudgingWalks(t *testing.T) {
	project := filepath.Clean(t.TempDir())
	serves := servingTheConfig(t, project)

	serves(`{"paths": ["src"]}`)
	heldTheConfigOf(map[string]any{"config": map[string]any{"version": float64(1)}})

	kept.Hold(project, "main", []string{project}, engine.Load(), nil)
	t.Cleanup(func() { kept.Drop(project, "main") })

	serves(`{"paths": ["src"], "detectors": ["SomethingElse"]}`)
	heldTheConfigOf(map[string]any{"config": map[string]any{"version": float64(2)}})

	if _, held := kept.Holds(project, "main"); !held {
		t.Fatal("a change that walks the same folders let the tree go")
	}

	serves(`{"paths": ["src", "lib"]}`)
	heldTheConfigOf(map[string]any{"config": map[string]any{"version": float64(3)}})

	if _, held := kept.Holds(project, "main"); held {
		t.Error("the config now walks lib/ as well, and the tree read without it is still held")
	}
}

func TestALineWithNoConfigVersionAsksTheJournalNothing(t *testing.T) {
	project := filepath.Clean(t.TempDir())
	serves := servingTheConfig(t, project)
	serves(`not json`)

	heldTheConfigOf(map[string]any{"event": "tool_starting"})

	if _, held := kept.Holds(project, "main"); held {
		t.Error("a line without a config version changed what is held")
	}
}
