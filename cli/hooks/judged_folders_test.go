package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestTheTreeHoldsAFolderARuleJudgesDespiteItsExclusion(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	t.Setenv(pluginData, t.TempDir())
	t.Setenv("JOURNAL_ROOT", root)

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src"], "exclude": ["tests"],
 "configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}}`,
		"src/__init__.py":       "",
		"src/order.py":          "class Order:\n    pass\n",
		"tests/__init__.py":     "",
		"tests/order_test.py":   "def test_order():\n    pass\n",
		"elsewhere/__init__.py": "",
		"elsewhere/unjudged.py": "x = 1\n",
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
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	settings, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	project, branch := git.ProjectRoot(root), git.CurrentBranch(root)
	kept.Drop(project, branch)
	t.Cleanup(func() { kept.Drop(project, branch) })

	Serves()
	t.Cleanup(func() { serving.Store(false) })

	Warming(root, settings)
	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}

	base, held := kept.Holds(project, branch)
	if !held {
		t.Fatal("the python bridge cannot run here, or the tree was not read")
	}

	if _, known := base.Holds(filepath.Join(root, "tests", "order_test.py")); !known {
		t.Error("the tree left out tests/, which a rule judges although it is excluded, so every judging the judge " +
			"walks there finds files the tree never read")
	}

	if _, known := base.Holds(filepath.Join(root, "elsewhere", "unjudged.py")); known {
		t.Error("the tree read elsewhere/, which no path declares and no rule judges")
	}
}
