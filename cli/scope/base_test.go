package scope_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// TestABareBranchComparesAgainstTheBranchTheProjectDeclares holds where the base of a bare `--branch` comes from.
// Nothing can work a branch's own base out reliably, so the project says it once: a project whose trunk is
// development judges what the branch changed since development, not everything development differs from main.
func TestABareBranchComparesAgainstTheBranchTheProjectDeclares(t *testing.T) {
	for name, test := range map[string]struct {
		declared string
		args     []string
		want     string
	}{
		"the project declares its trunk": {declared: "development", args: []string{"--branch"}, want: "development"},
		"the command line names a base":  {declared: "development", args: []string{"--branch=named-on-the-line"}, want: "named-on-the-line"},
		"nothing declares one":           {declared: "", args: []string{"--branch"}, want: "main"},
		"the declaration is blank":       {declared: "   ", args: []string{"--branch"}, want: "main"},
	} {
		t.Run(name, func(t *testing.T) {
			root := projectDeclaring(t, test.declared)

			chosen, err := scope.FromArgs(test.args, root, workspace.At(root, ""), nil)
			if err != nil {
				t.Fatalf("the scope was refused: %v", err)
			}

			if !strings.Contains(chosen.ChosenBy(), "since "+test.want) {
				t.Errorf("the scope chose %q, and the base should be %q", chosen.ChosenBy(), test.want)
			}
		})
	}
}

// projectDeclaring is a project whose config declares the base, or declares none when it is empty.
func projectDeclaring(t *testing.T, base string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	config := `{"paths": ["src"]}`
	if base != "" {
		config = `{"paths": ["src"], "base": "` + base + `"}`
	}

	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".commandments", "config.json"), []byte(config+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	// A scope of a branch is read from git, so the project is one: a repository whose trunk is whatever the test
	// declared, with one commit for a branch to have left, and the branch a test names on the command line.
	trunk := strings.TrimSpace(base)
	if trunk == "" {
		trunk = "main"
	}

	for _, run := range [][]string{
		{"init", "-q", "-b", trunk},
		{"config", "user.email", "t@t"},
		{"config", "user.name", "T"},
		{"add", "-A"},
		{"commit", "-qm", "the project as it stands"},
		{"branch", "named-on-the-line"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, run...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", run, out)
		}
	}

	return root
}
