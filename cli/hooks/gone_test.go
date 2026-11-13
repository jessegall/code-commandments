package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestTheSinsOfAFileThatIsGoneLeaveTheRecordWithoutCountingAsRepented(t *testing.T) {
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
	if len(settlement.Found) != 0 || len(settlement.Repented) != 0 {
		t.Errorf("a removed file's sins were found %v or repented %v, and removing a file fixes nothing", settlement.Found, settlement.Repented)
	}

	if raised := settlement.Raises(root); len(settlement.Settles()) != 0 || len(raised) != 0 {
		t.Errorf("a removed file settles %v and raises %v, a clean mark for a file that was only deleted", settlement.Settles(), raised)
	}

	kept, _ := os.ReadFile(filepath.Join(data, "sins.json"))
	if strings.Contains(string(kept), "Gone.php") || !strings.Contains(string(kept), "src/Here.php") {
		t.Errorf("the record kept %s", kept)
	}
}

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

	AnnouncedIn(data, Moment{Env: &own}).Settle(main, nil, nil)
	if kept, _ := os.ReadFile(filepath.Join(data, "sins.main.json")); strings.Contains(string(kept), "probe.py") {
		t.Errorf("main's own moment kept the sins of a file it no longer holds: %s", kept)
	}
}
