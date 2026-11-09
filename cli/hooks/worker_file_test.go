package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
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

func TestAWorkersOwnFileIsMarkedWhereItIsJudgedAlone(t *testing.T) {
	root, data := workerProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)

	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	Judge4Into(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 545,
		"data": map[string]any{
			"path": ".journal/worktrees/worker-1/src/order.py", "kind": "edit", "added": 3, "removed": 0, "tool_use": 41,
			"lines": []any{
				map[string]any{"line_type": "add", "line": 2, "text": "    def total(self):"},
				map[string]any{"line_type": "add", "line": 3, "text": `        """Says the total."""`},
			},
		},
	}, data)

	worker := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")

	if judged := judgedFile(root, worker, settings); judged != worker {
		t.Fatalf("the worker's file is judged as %q", judged)
	}

	rules := settings.RulesFor(checkoutOf(root, worker), worker, allRules(t, root, settings))
	marks, _, _ := marksIn(Marks(root), worker, rules, git.ChangedLines{})

	if len(marks) != 1 {
		t.Fatalf("the project's rules find %d sins in the worker's own file", len(marks))
	}

	if named := marks[0].Found(); !strings.Contains(named, "documented-anywhere") {
		t.Errorf("the mark names %q", named)
	}

	against, _, whole := AgainstTheProgram(Marks(root), worker, rules, git.ChangedLines{}, root)
	if !whole {
		t.Fatal("a worker's edit is not judged against the project's tree at all")
	}

	if len(against) != 1 {
		t.Errorf("judged against the tree, the worker's file carries %d marks, want the one its sin is", len(against))
	}
}

func TestAWorkersEarlierWorkIsInTheProgramItsNextEditIsJudgedAgainst(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	settings, _ := config.Load(root)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	earlier := filepath.Join(worktree, "src", "invoice.py")
	if err := os.WriteFile(earlier, []byte("class Invoice:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	own := filepath.Join(worktree, "src", "order.py")
	if err := os.WriteFile(own, []byte(`class Order:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	rules := settings.RulesFor(checkoutOf(root, own), own, allRules(t, root, settings))

	marks, _, whole := AgainstTheProgram(Marks(root), own, rules, git.ChangedLines{}, root)
	if !whole {
		t.Fatal("the worker's edit is not judged against the program at all")
	}

	if len(marks) != 1 {
		t.Errorf("the worker's own file carries %d marks, want the one its sin is", len(marks))
	}

	if !kept.Contents().Knows(earlier) {
		t.Errorf("the worker's earlier %s is not in the program its next edit is judged against", earlier)
	}
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
