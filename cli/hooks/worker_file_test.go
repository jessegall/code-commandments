package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/detectors"
)

func TestAWorkersFileInAWorktreeIsJudged(t *testing.T) {
	root, _ := workerProject(t)

	project, _ := config.Load(root)

	own := filepath.Join(root, "src", "order.py")
	if judged := judgedFile(root, own, project); judged != own {
		t.Errorf("the project's own file is judged as %q", judged)
	}

	worker := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")
	if judged := judgedFile(root, worker, project); judged != worker {
		t.Errorf("a worker's file, named from the project, is judged as %q; a worker's edits are then never judged", judged)
	}

	if err := os.RemoveAll(filepath.Join(root, ".journal", "worktrees", "worker-1", ".commandments")); err != nil {
		t.Fatal(err)
	}

	if judged := judgedFile(root, worker, project); judged != worker {
		t.Errorf("with no configuration of its own the worker's file is judged as %q", judged)
	}

	if err := os.MkdirAll(filepath.Join(root, ".journal", "worktrees", "worker-1", ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, ".journal", "worktrees", "worker-1", ".commandments", "config.json"), []byte(`{"paths": ["nowhere"]}`), 0o644); err != nil {
		t.Fatal(err)
	}

	asWorktree, err := config.Load(filepath.Join(root, ".journal", "worktrees", "worker-1"))
	if err != nil {
		t.Fatal(err)
	}

	if judged := judgedFile(root, worker, asWorktree); judged != worker {
		t.Errorf("a worktree's own configuration judged the worker's file as %q; only the project's may", judged)
	}
}

func allRules(t *testing.T, root string, project config.Config) []detectors.Detector {
	t.Helper()

	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}

	return append(enabled, custom.InForce(root).Enabled(project)...)
}

func workerProject(t *testing.T) (root, data string) {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data = t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("JOURNAL_ROOT", root)

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src"], "detectors": ["DocumentedAnywhereDetector"]}`,
		".commandments/custom/DocumentedAnywhereDetector.json": `{"engine": "python",
 "sin": {"name": "documented-anywhere", "description": "a documented method", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		"src/__init__.py": "",
		"src/order.py":    "class Order:\n    pass\n",
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
		{"worktree", "add", "-q", "-b", "a-worker", filepath.Join(root, ".journal", "worktrees", "worker-1")},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	if err := os.WriteFile(filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py"), []byte(`class Order:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	return root, data
}

func TestAWorktreesOwnConfigIsNeverRead(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	own := filepath.Join(worktree, ".commandments", "config.json")
	if err := os.MkdirAll(filepath.Dir(own), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(own, []byte(`{"paths": ["nothing"], "detectors": []}`), 0o644); err != nil {
		t.Fatal(err)
	}

	settings, err := config.Load(projectOf(worktree))
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(settings.Paths, "src") {
		t.Errorf("the settings judge %v; the project declares src, so the worktree's own config was read", settings.Paths)
	}

	file := filepath.Join(worktree, "src", "order.py")
	if judged := judgedFile(worktree, file, settings.Judging(allRules(t, root, settings))); judged == "" {
		t.Error("a worker's own file is judged by nothing, so the config came from its worktree")
	}
}
