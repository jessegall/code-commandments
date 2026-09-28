package roslyn

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
		code, err := HasCSharp{}.Run(&cli.Input{}, cli.Console{Out: &out, Err: &out})
		os.Chdir(was)

		if err != nil || code != want || !strings.Contains(out.String(), "C# to judge") {
			t.Errorf("%s: exit %d (%v), said %q", file, code, err, out.String())
		}
	}
}
