package bundle

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cache"
)

func TestASweepLetsGoOfTreesNotUsedForTheTimeACacheIsKept(t *testing.T) {
	folder := t.TempDir()
	now := time.Now()
	stale, fresh := filepath.Join(folder, "stale.tree"), filepath.Join(folder, "fresh.tree")
	for _, tree := range []string{stale, fresh} {
		if err := os.WriteFile(tree, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	old := now.Add(-cache.KeptFor() - time.Hour)
	if err := os.Chtimes(stale, old, old); err != nil {
		t.Fatal(err)
	}

	Sweep(folder, now)
	if _, err := os.Stat(stale); !os.IsNotExist(err) {
		t.Error("a tree unused past the time a cache is kept was kept")
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
	Sweep(folder, now.Add(30*time.Minute))
	if _, err := os.Stat(stale); err != nil {
		t.Error("a folder swept within the hour was walked again")
	}
	Sweep(folder, now.Add(61*time.Minute))
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

func TestAFolderNotTouchedForTheTimeACacheIsKeptIsForgottenAndATouchedOneKept(t *testing.T) {
	parent := t.TempDir()
	now := time.Now()
	idle, used := filepath.Join(parent, "idle"), filepath.Join(parent, "used")

	for _, folder := range []string{idle, used} {
		if err := os.MkdirAll(folder, 0o755); err != nil {
			t.Fatal(err)
		}
	}

	old := now.Add(-cache.KeptFor() - time.Hour)
	if err := os.Chtimes(idle, old, old); err != nil {
		t.Fatal(err)
	}

	if err := os.Chtimes(used, old, old); err != nil {
		t.Fatal(err)
	}

	if err := os.Chtimes(used, now, now); err != nil {
		t.Fatal(err)
	}

	ForgetUnused(parent, now)

	if _, err := os.Stat(idle); !os.IsNotExist(err) {
		t.Error("a folder untouched past the time a cache is kept was kept")
	}

	if _, err := os.Stat(used); err != nil {
		t.Errorf("a folder touched just now was forgotten: %v", err)
	}
}

func TestASweepAndAForgettingKeepAsLongAsTheProjectChose(t *testing.T) {
	t.Setenv(cache.Variable, "2")

	folder, parent := t.TempDir(), t.TempDir()
	now := time.Now()
	stale, fresh := filepath.Join(folder, "stale.tree"), filepath.Join(folder, "fresh.tree")
	idle, used := filepath.Join(parent, "idle"), filepath.Join(parent, "used")

	for _, tree := range []string{stale, fresh} {
		if err := os.WriteFile(tree, []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	for _, each := range []string{idle, used} {
		if err := os.MkdirAll(each, 0o755); err != nil {
			t.Fatal(err)
		}
	}

	threeHours, oneHour := now.Add(-3*time.Hour), now.Add(-time.Hour)
	for path, at := range map[string]time.Time{stale: threeHours, fresh: oneHour, idle: threeHours, used: oneHour} {
		if err := os.Chtimes(path, at, at); err != nil {
			t.Fatal(err)
		}
	}

	Sweep(folder, now)
	ForgetUnused(parent, now)

	for path, kept := range map[string]bool{stale: false, fresh: true, idle: false, used: true} {
		if _, err := os.Stat(path); (err == nil) != kept {
			t.Errorf("with two hours chosen, %s kept is %v, want %v", filepath.Base(path), err == nil, kept)
		}
	}
}
