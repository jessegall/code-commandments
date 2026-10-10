package service

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// TestHasCSharpAnswersWhatTheProjectHolds holds the verb the journal asks before it starts the roslyn service: 0 for
// a project with C#, 1 for one with none, saying which either way.
func TestHasCSharpAnswersWhatTheProjectHolds(t *testing.T) {
	for file, want := range map[string]int{"src/Cart.cs": 0, "src/cart.py": cli.Refused} {
		project := t.TempDir()
		if err := os.MkdirAll(filepath.Join(project, "src"), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(project, file), []byte("x"), 0o644); err != nil {
			t.Fatal(err)
		}

		was, _ := os.Getwd()
		os.Chdir(project)
		t.Setenv("CLAUDE_PROJECT_DIR", project)

		var out bytes.Buffer
		code, err := Has{CSharp}.Run(&cli.Input{}, cli.Console{Out: &out, Err: &out})
		os.Chdir(was)

		if err != nil || code != want || !strings.Contains(out.String(), "C# to judge") {
			t.Errorf("%s: exit %d (%v), said %q", file, code, err, out.String())
		}
	}
}

// TestHasCSharpAnswersForTheProjectTheJournalNames holds it to the project the journal runs it for: the journal asks
// from the plugin's own folder, a checkout with C# of its own, and names the project by its .journal folder.
func TestHasCSharpAnswersForTheProjectTheJournalNames(t *testing.T) {
	plugin, project := t.TempDir(), t.TempDir()
	for path, text := range map[string]string{filepath.Join(plugin, "bridge", "Bridge.cs"): "x", filepath.Join(project, "src", "cart.py"): "x"} {
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	was, _ := os.Getwd()
	os.Chdir(plugin)
	defer os.Chdir(was)
	t.Setenv("CLAUDE_PROJECT_DIR", "")
	t.Setenv("JOURNAL_ROOT", filepath.Join(project, ".journal"))

	var out bytes.Buffer
	code, err := Has{CSharp}.Run(&cli.Input{}, cli.Console{Out: &out, Err: &out})

	if err != nil || code != cli.Refused || !strings.Contains(out.String(), project+" has no C#") {
		t.Errorf("exit %d (%v), said %q", code, err, out.String())
	}
}
