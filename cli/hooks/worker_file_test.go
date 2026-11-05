package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/detectors"
)

// TestAWorkersFileInAWorktreeIsJudged holds the gate that drops nothing a worker writes. A worktree is a
// checkout of its own inside the project's hidden folder, so a file in it, named from the project, reads as a
// path outside every declared root: a worker's edits were never judged at all and its events were answered with
// nothing, while a judging of the same file by its absolute path found eight sins. Reported from a consumer on
// v7.8.1 for worker 545.
func TestAWorkersFileInAWorktreeIsJudged(t *testing.T) {
	root, _ := workerProject(t)

	// The project's own configuration, which is the one a worker is judged by: it keeps no copy of its own, so
	// a change here reaches every worker at once.
	project, _ := config.Load(root)

	own := filepath.Join(root, "src", "order.py")
	if judged := judgedFile(root, own, project); judged != own {
		t.Errorf("the project's own file is judged as %q", judged)
	}

	worker := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")
	if judged := judgedFile(root, worker, project); judged != worker {
		t.Errorf("a worker's file, named from the project, is judged as %q; a worker's edits are then never judged", judged)
	}

	// The worktree keeps no configuration of its own, and is judged all the same: the folders come from the
	// project and only the root they are joined to is the worktree's.
	if err := os.RemoveAll(filepath.Join(root, ".journal", "worktrees", "worker-1", ".commandments")); err != nil {
		t.Fatal(err)
	}

	if judged := judgedFile(root, worker, project); judged != worker {
		t.Errorf("with no configuration of its own the worker's file is judged as %q", judged)
	}

	// And a worktree that DOES hold one of its own is not judged by it: a worker's branch may say anything,
	// and what judges it is the configuration beside the plugin, so a change there reaches every worker at once.
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

// allRules are the project's enabled rules, its own among them.
func allRules(t *testing.T, root string, project config.Config) []detectors.Detector {
	t.Helper()

	enabled, err := project.Enabled(config.InstalledIn(root))
	if err != nil {
		t.Fatal(err)
	}

	return append(enabled, custom.InForce(root).Enabled(project)...)
}

// workerProject is a project with a rule of its own, a worktree cut inside its hidden folder as the journal cuts
// one, and a sinful file in each checkout, with the plugin's data folder.
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

	// Both checkouts are real ones, which is what makes the worktree a checkout of its own rather than a folder.
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

	// The worker writes the sin in its own checkout.
	if err := os.WriteFile(filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py"), []byte(`class Order:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	return root, data
}

// TestAWorkersOwnFileIsMarkedWhereItIsJudgedAlone holds the rules reaching a worker's own file. A worktree's
// file has a path of its own, so the base's copy of the same source is a different file: splicing the worker's in
// would leave the program holding both, and a rule reading a recurrence or a declaration would answer against two
// of everything the worker changed. So such an edit is judged alone, with the project's own rules, and this holds
// that they find it.
func TestAWorkersOwnFileIsMarkedWhereItIsJudgedAlone(t *testing.T) {
	root, data := workerProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)

	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase)
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
	marks := marksIn(Marks(root), worker, rules, git.ChangedLines{})

	if len(marks) != 1 {
		t.Fatalf("the project's rules find %d sins in the worker's own file", len(marks))
	}

	if named := marks[0].Found(); !strings.Contains(named, "documented-anywhere") {
		t.Errorf("the mark names %q", named)
	}

	// The base is held for the project, and an edit in another checkout of it is judged alone rather than
	// against a program holding two copies of the worker's file.
	if _, whole := AgainstTheProgram(Marks(root), worker, rules, git.ChangedLines{}, root); whole {
		t.Error("a worker's file was judged against the project's tree, which holds the base's copy of it too")
	}
}
