package dashboard

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"testing"

	"github.com/jessegall/code-commandments/cli/jsonfile"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"

	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheDashboardAndStoreAreWrittenAsPhpWritesThem(t *testing.T) {
	raw, _ := os.ReadFile("testdata/stored.json")

	var stored []Stored
	if err := json.Unmarshal(raw, &stored); err != nil {
		t.Fatal(err)
	}

	for golden, render := range map[string]func() (string, error){
		"testdata/stored.json": func() (string, error) { return jsonfile.Pretty(stored, false) },
		"testdata/sins.json":   func() (string, error) { return jsonfile.Pretty(Render(stored), true) },
		"testdata/empty.json":  func() (string, error) { return jsonfile.Pretty(Render(nil), true) },
	} {
		got, err := render()
		want, _ := os.ReadFile(golden)

		if err != nil || got != string(want) {
			t.Errorf("%s differs (%v)\n--- want\n%s\n--- got\n%s", golden, err, want, got)
		}
	}
}

// TestARunReplacesOnlyTheFindingsOfTheFilesItJudged holds the store to the files a run judged: a narrower run keeps
// what it did not judge, a file it judged clean loses its findings, and a file that is gone takes its findings along.
func TestARunReplacesOnlyTheFindingsOfTheFilesItJudged(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	space := workspace.At(root, "")
	finding := func(name string) engine.Finding {
		path := filepath.Join(root, name)
		if err := os.WriteFile(path, []byte("<?php\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		return engine.Finding{Skill: "backend/absence", Sin: "a-sin", File: path, Location: path + ":1", Scope: "S"}
	}
	paths := func(names ...string) map[string]bool {
		judged := map[string]bool{}
		for _, name := range names {
			judged[filepath.Join(root, name)] = true
		}

		return judged
	}
	stored := func() []string {
		var files []string
		for _, each := range storedIn(space) {
			files = append(files, each.File)
		}
		sort.Strings(files)

		return files
	}

	order, cart, gone := finding("Order.php"), finding("Cart.php"), finding("Gone.php")
	if err := Record(space, []engine.Finding{order, cart, gone}, paths("Order.php", "Cart.php", "Gone.php")); err != nil {
		t.Fatal(err)
	}
	if err := Record(space, nil, paths("Order.php")); err != nil {
		t.Fatal(err)
	}
	if got := stored(); !slices.Equal(got, []string{"Cart.php", "Gone.php"}) {
		t.Errorf("a run judging Order.php clean keeps %v", got)
	}
	os.Remove(gone.File)
	if err := Record(space, nil, paths("Order.php")); err != nil {
		t.Fatal(err)
	}
	if got := stored(); !slices.Equal(got, []string{"Cart.php"}) {
		t.Errorf("once Gone.php is deleted the store keeps %v", got)
	}
}
