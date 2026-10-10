package bundle

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

// TestASweepLetsGoOfTreesNotWrittenForTwoWeeks holds the trees folder to a bound: a tree no run has written for two
// weeks goes, a fresh one stays, and a folder swept within the day is not walked again.
func TestASweepLetsGoOfTreesNotWrittenForTwoWeeks(t *testing.T) {
	folder := t.TempDir()
	now := time.Now()
	stale, fresh := filepath.Join(folder, "stale.tree"), filepath.Join(folder, "fresh.tree")
	for _, tree := range []string{stale, fresh} {
		if err := os.WriteFile(tree, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	old := now.Add(-15 * 24 * time.Hour)
	if err := os.Chtimes(stale, old, old); err != nil {
		t.Fatal(err)
	}

	sweep(folder, now)
	if _, err := os.Stat(stale); !os.IsNotExist(err) {
		t.Error("a tree unwritten for fifteen days was kept")
	}
	if _, err := os.Stat(fresh); err != nil {
		t.Errorf("a fresh tree was swept: %v", err)
	}

	if err := os.WriteFile(stale, []byte("{}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.Chtimes(stale, old, old); err != nil {
		t.Fatal(err)
	}
	sweep(folder, now.Add(time.Hour))
	if _, err := os.Stat(stale); err != nil {
		t.Error("a folder swept within the day was walked again")
	}
	sweep(folder, now.Add(25*time.Hour))
	if _, err := os.Stat(stale); !os.IsNotExist(err) {
		t.Error("the next day's sweep kept a stale tree")
	}
}
