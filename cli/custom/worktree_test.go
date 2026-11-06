package custom_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	_ "github.com/jessegall/code-commandments/registry"
)

const ownRule = `{"engine": "python", "find": {"select": "function", "where": [{"documented": true}]},
 "sin": {"name": "docstring-in-method", "description": "a docstring inside a method", "skill": "python/documentation"}}`

func TestAWorktreeJudgesByTheProjectsOwnRules(t *testing.T) {
	project := repository(t)

	write(t, filepath.Join(project, ".commandments", "custom", "DocstringInMethodDetector.json"), ownRule)
	run(t, project, "add", "-A")
	run(t, project, "commit", "-m", "the project's own rule")

	worktree := filepath.Join(t.TempDir(), "work")
	run(t, project, "worktree", "add", "-b", "a-worker", worktree, "HEAD~1")

	own := custom.InForce(worktree)
	if len(own.Rules) != 1 {
		t.Fatalf("a worktree reads %d of the project's own rules, want the 1 its owner committed", len(own.Rules))
	}

	if named, found := own.Named("DocstringInMethodDetector"); !found || named.Sin().Definition().Name != "docstring-in-method" {
		t.Errorf("the rule the config turns on is not the one read: %+v", named)
	}
}

func TestARuleIsAuthoredInTheTreeItIsWrittenIn(t *testing.T) {
	project := repository(t)
	run(t, project, "add", "-A")
	run(t, project, "commit", "--allow-empty", "-m", "no rules yet")

	worktree := filepath.Join(t.TempDir(), "work")
	run(t, project, "worktree", "add", "-b", "an-author", worktree)
	write(t, filepath.Join(worktree, ".commandments", "custom", "DocstringInMethodDetector.json"), ownRule)

	if authored := custom.Load(worktree); len(authored.Rules) != 1 {
		t.Errorf("a rule written in the worktree is not read there: %d rules", len(authored.Rules))
	}

	if inForce := custom.InForce(worktree); len(inForce.Rules) != 0 {
		t.Errorf("a rule only written in a worktree already judges the project: %d rules", len(inForce.Rules))
	}
}

func TestTheConfigAndTheRulesComeFromOneTree(t *testing.T) {
	project := repository(t)

	write(t, filepath.Join(project, ".commandments", "config.json"), `{"paths": ["src"], "detectors": ["DocstringInMethodDetector"]}`)
	write(t, filepath.Join(project, ".commandments", "custom", "DocstringInMethodDetector.json"), ownRule)
	run(t, project, "add", "-A")
	run(t, project, "commit", "-m", "the rule and the config line that turns it on")

	worktree := filepath.Join(t.TempDir(), "work")
	run(t, project, "worktree", "add", "-b", "a-worker", worktree, "HEAD~1")

	for _, where := range []struct {
		what string
		dir  string
	}{
		{"the main checkout", project},
		{"a worktree cut before the rule", worktree},
		{"a folder deep inside a worktree", filepath.Join(worktree, "src")},
	} {
		read, err := config.Load(where.dir)
		if err != nil {
			t.Fatalf("%s: %v", where.what, err)
		}

		enabled := custom.InForce(where.dir).Enabled(read)
		if len(enabled) != 1 {
			t.Errorf("%s: the config turns on %v, and %d of the project's own rules answer to it", where.what, read.Detectors, len(enabled))

			continue
		}

		if name := catalog.Name(enabled[0]); name != "DocstringInMethodDetector" {
			t.Errorf("%s: the rule in force is %s", where.what, name)
		}
	}
}

func TestAFolderOutsideGitKeepsItsOwnRules(t *testing.T) {
	root := t.TempDir()
	write(t, filepath.Join(root, ".commandments", "custom", "DocstringInMethodDetector.json"), ownRule)

	if own := custom.InForce(root); len(own.Rules) != 1 {
		t.Errorf("a folder in no repository does not read the rules beside it: %d rules", len(own.Rules))
	}
}

func repository(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	run(t, root, "init", "-b", "main")
	run(t, root, "config", "user.email", "test@example.com")
	run(t, root, "config", "user.name", "A Test")
	write(t, filepath.Join(root, "src", "a.py"), "x = 1\n")
	run(t, root, "add", "-A")
	run(t, root, "commit", "-m", "first")

	return root
}

func run(t *testing.T, root string, args ...string) {
	t.Helper()

	command := exec.Command("git", args...)
	command.Dir = root
	if out, err := command.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v\n%s", args, err, out)
	}
}

func write(t *testing.T, path, contents string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}
}
