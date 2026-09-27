package sync

import (
	"encoding/json"
	"io/fs"
	"os"
	"path/filepath"
	"reflect"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// sessionFiles are what an old project's session folders hold, by path under the project.
var sessionFiles = []string{
	".commandments/sessions/a/.until-stop",
	".commandments/sessions/a/.plan-1",
	".commandments/sessions/a/.constraints-verified",
	".commandments/sessions/a/sins.md",
	".commandments/sessions/a/sins-2.md",
	".commandments/sessions/a/.judge-count",
	".commandments/sessions/b/sins.md",
	".commandments/sessions/b/sins/sins.md",
	".commandments/sessions/b/kept.txt",
}

// TestTheStateMigratesAsThePHPToolMigratesIt holds the migration to what the PHP tool did and left for each case,
// recorded under testdata/migrated/<case>: the lines it printed and the files it left.
func TestTheStateMigratesAsThePHPToolMigratesIt(t *testing.T) {
	for name, journal := range map[string]bool{"own": false, "journal": true} {
		t.Run(name, func(t *testing.T) {
			root := t.TempDir()
			for _, file := range sessionFiles {
				must(t, os.MkdirAll(filepath.Dir(filepath.Join(root, file)), 0o755))
				must(t, os.WriteFile(filepath.Join(root, file), []byte(file), 0o644))
			}
			if journal {
				must(t, os.MkdirAll(filepath.Join(root, ".journal"), 0o755))
			}

			done := strings.Join(Migrate(workspace.At(root, "")), "\n")

			recorded := filepath.Join("testdata", "migrated", name)
			said, _ := os.ReadFile(filepath.Join(recorded, "answer"))
			if done != string(said) {
				t.Errorf("did\n%s\nthe PHP tool did\n%s", done, said)
			}

			var want map[string]string
			left, _ := os.ReadFile(filepath.Join(recorded, "tree.json"))
			must(t, json.Unmarshal(left, &want))
			if got := tree(root); !reflect.DeepEqual(got, want) {
				t.Errorf("left\n%v\nthe PHP tool left\n%v", got, want)
			}

			if again := Migrate(workspace.At(root, "")); len(again) != 0 {
				t.Errorf("migrated twice: %v", again)
			}
		})
	}
}

func tree(root string) map[string]string {
	files := map[string]string{}

	filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		if err == nil && entry.Type().IsRegular() {
			contents, _ := os.ReadFile(path)
			relative, _ := filepath.Rel(root, path)
			files[relative] = string(contents)
		}

		return nil
	})

	return files
}

func must(t *testing.T, err error) {
	t.Helper()

	if err != nil {
		t.Fatal(err)
	}
}
