package sync

import (
	"io/fs"
	"os"
	"os/exec"
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

func TestTheStateMigratesAsThePHPToolMigratesIt(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("no php to migrate with the PHP tool")
	}

	for name, journal := range map[string]bool{"the tool's own folder": false, "the journal's folder": true} {
		t.Run(name, func(t *testing.T) {
			php, golang := t.TempDir(), t.TempDir()

			for _, root := range []string{php, golang} {
				for _, file := range sessionFiles {
					must(t, os.MkdirAll(filepath.Dir(filepath.Join(root, file)), 0o755))
					must(t, os.WriteFile(filepath.Join(root, file), []byte(file), 0o644))
				}

				if journal {
					must(t, os.MkdirAll(filepath.Join(root, ".journal"), 0o755))
				}
			}

			repo, _ := filepath.Abs("../..")
			script := `require '` + repo + `/vendor/autoload.php';
echo implode("\n", (new \JesseGall\CodeCommandments\Cli\Migration(\JesseGall\CodeCommandments\Workspace::at($argv[1])))->run());`

			out, err := exec.Command("php", "-r", script, "--", php).CombinedOutput()
			if err != nil {
				t.Fatalf("php: %v\n%s", err, out)
			}

			done := strings.Join(Migrate(workspace.At(golang, "")), "\n")

			if done != string(out) {
				t.Errorf("did\n%s\nthe PHP tool did\n%s", done, out)
			}

			if want, got := tree(php), tree(golang); !reflect.DeepEqual(got, want) {
				t.Errorf("left\n%v\nthe PHP tool left\n%v", got, want)
			}

			if again := Migrate(workspace.At(golang, "")); len(again) != 0 {
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
