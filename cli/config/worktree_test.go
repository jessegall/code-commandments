package config_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

func TestAWorktreeIsJudgedByTheProjectsConfig(t *testing.T) {
	project := repository(t)

	write(t, filepath.Join(project, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["vendor"]}`)
	run(t, project, "add", "-A")
	run(t, project, "commit", "-m", "the project's own config")

	worktree := filepath.Join(t.TempDir(), "work")
	run(t, project, "worktree", "add", "-b", "a-worker", worktree)

	write(t, filepath.Join(worktree, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["everything-the-worker-wrote"]}`)

	read, err := config.Load(worktree)
	if err != nil {
		t.Fatal(err)
	}

	if slices.Contains(read.Excluded, "everything-the-worker-wrote") {
		t.Error("a worktree is judged by its own branch's config, so a worker can exclude itself from judgement")
	}

	if !slices.Contains(read.Excluded, "vendor") {
		t.Errorf("the project's own excludes do not reach the worktree: %v", read.Excluded)
	}
}

func TestAFileTheProjectExcludesIsNotJudgedInACheckoutOrAWorktree(t *testing.T) {
	project := config.Config{
		Paths:    []string{"src", "tools/hook"},
		Excluded: []string{"**/dist", "**/vendor", "src/viewer/tests/sample-window", "tests", "tools/hook/testdata"},
	}

	for _, root := range []string{
		"/project",
		"/project/.journal/worktrees/worker-1",
		"/elsewhere/worker-1",
		"/home/dist/vendor/tests/project",
	} {
		for file, judged := range map[string]bool{
			"src/core/modules/viewer/web/dist/assets/index-7UEM03nZ.js": false,
			"src/dist/index.js":                     false,
			"src/core/vendor/lib/a.php":             false,
			"src/viewer/tests/sample-window/a.ts":   false,
			"tools/hook/testdata/a.go":              false,
			"tests/test_order.py":                   false,
			"src/core/order.py":                     true,
			"src/distant/order.py":                  true,
			"src/viewer/tests/other-window/a.ts":    true,
			"tools/hook/main.go":                    true,
		} {
			if got := project.IsJudged(root, root+"/"+file); got != judged {
				t.Errorf("under %s, %s is judged=%v, want %v", root, file, got, judged)
			}
		}
	}
}

func TestAFolderARuleJudgesStaysJudgedDespiteItsExclusion(t *testing.T) {
	project := config.Config{
		Paths:    []string{"src"},
		Excluded: []string{"tests"},
		Configurators: []config.Configurator{{
			Target: config.RuleNamed(config.Detector, "AnyDetector"),
			Calls:  []config.Call{{Method: config.Judges, Args: []config.Arg{{Value: []any{"tests"}}}}},
		}},
	}

	if !project.IsJudged("/project", "/project/tests/test_order.py") {
		t.Error("a folder a rule of the project judges is left out by the exclusion that rule is written to pass")
	}
}

func TestAProjectOutsideGitIsItsOwnSettingsRoot(t *testing.T) {
	root := t.TempDir()
	write(t, filepath.Join(root, ".commandments", "config.json"), `{"paths": ["src"], "exclude": ["dist"]}`)

	read, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(read.Excluded, "dist") {
		t.Errorf("a folder in no repository does not read its own config: %v", read.Excluded)
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
	write(t, filepath.Join(root, "src", "a.txt"), "a\n")
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
