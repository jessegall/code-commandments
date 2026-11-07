package bundle

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestASweepLetsGoOfTreesNotWrittenForThreeDays(t *testing.T) {
	folder := t.TempDir()
	now := time.Now()
	stale, fresh := filepath.Join(folder, "stale.tree"), filepath.Join(folder, "fresh.tree")
	for _, tree := range []string{stale, fresh} {
		if err := os.WriteFile(tree, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	old := now.Add(-4 * 24 * time.Hour)
	if err := os.Chtimes(stale, old, old); err != nil {
		t.Fatal(err)
	}

	sweep(folder, now)
	if _, err := os.Stat(stale); !os.IsNotExist(err) {
		t.Error("a tree unwritten for four days was kept")
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
	sweep(folder, now.Add(30*time.Minute))
	if _, err := os.Stat(stale); err != nil {
		t.Error("a folder swept within the hour was walked again")
	}
	sweep(folder, now.Add(61*time.Minute))
	if _, err := os.Stat(stale); !os.IsNotExist(err) {
		t.Error("the next hour's sweep kept a stale tree")
	}
}

func TestASweepKeepsTheCacheUnderItsSizeByLettingTheOldestTreesGo(t *testing.T) {
	folder := t.TempDir()
	now := time.Now()

	for age, name := range []string{"newest.tree", "middle.tree", "oldest.tree"} {
		path := filepath.Join(folder, name)
		if err := os.WriteFile(path, make([]byte, 100), 0o644); err != nil {
			t.Fatal(err)
		}

		written := now.Add(-time.Duration(age+1) * time.Hour)
		if err := os.Chtimes(path, written, written); err != nil {
			t.Fatal(err)
		}
	}

	sweepKeeping(folder, now, 250)

	for name, kept := range map[string]bool{"newest.tree": true, "middle.tree": true, "oldest.tree": false} {
		_, err := os.Stat(filepath.Join(folder, name))
		if (err == nil) != kept {
			t.Errorf("after a sweep holding 300 bytes against a limit of 250, %s kept is %v, want %v: the "+
				"oldest trees go first until the cache fits", name, err == nil, kept)
		}
	}
}
