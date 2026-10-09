package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
)

// TestASinOfAFileThatIsGoneIsRepented holds a shell deletion to what an edit would say: every sin announced for a
// file that no longer exists is repented and its chat mark settled, and a file still there keeps what was announced for it.
func TestASinOfAFileThatIsGoneIsRepented(t *testing.T) {
	root, data := t.TempDir(), t.TempDir()
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src/Here.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	record := `{"src/Gone.php":{"a1":"array-bag at src/Gone.php:3"},"src/Here.php":{"b2":"array-bag at src/Here.php:2"}}`
	if err := os.WriteFile(filepath.Join(data, "sins.json"), []byte(record), 0o644); err != nil {
		t.Fatal(err)
	}

	settlement := AnnouncedIn(data, Moment{}).Settle(root, nil, nil)
	if len(settlement.Found) != 0 || !slices.Equal(settlement.Repented, []Repented{{"sin:a1", "array-bag at src/Gone.php:3"}}) {
		t.Errorf("found %v, repented %v", settlement.Found, settlement.Repented)
	}

	if !slices.Equal(settlement.Settles(), []string{"sin:a1"}) {
		t.Errorf("settles %v", settlement.Settles())
	}

	kept, _ := os.ReadFile(filepath.Join(data, "sins.json"))
	if strings.Contains(string(kept), "Gone.php") || !strings.Contains(string(kept), "src/Here.php") {
		t.Errorf("the record kept %s", kept)
	}
}

// TestAnotherEnvironmentNeverRepentsTheSinsAnnouncedInThisOne holds the record to the environment whose chat shows
// the sins: a moment in a helper's worktree, where a file announced in the main checkout does not exist, repents
// nothing of main's, and main's own moment still settles it once the file is gone.
func TestAnotherEnvironmentNeverRepentsTheSinsAnnouncedInThisOne(t *testing.T) {
	main, worktree, data := t.TempDir(), t.TempDir(), t.TempDir()
	record := `{"src/probe.py":{"a1":"redundant-python-else at src/probe.py:2"}}`
	if err := os.WriteFile(filepath.Join(data, "sins.main.json"), []byte(record), 0o644); err != nil {
		t.Fatal(err)
	}

	helper, own := "main-benny", "main"
	if settlement := AnnouncedIn(data, Moment{Env: &helper}).Settle(worktree, nil, nil); len(settlement.Settles()) != 0 {
		t.Errorf("the helper's moment settled %v", settlement.Settles())
	}

	if settlement := AnnouncedIn(data, Moment{Env: &own}).Settle(main, nil, nil); !slices.Equal(settlement.Settles(), []string{"sin:a1"}) {
		t.Errorf("main's own moment settled %v", settlement.Settles())
	}
}
