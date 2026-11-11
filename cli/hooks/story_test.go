package hooks

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

func TestTheStoryOfAProjectWithAMainAgentAndItsWorkers(t *testing.T) {
	root := storyProject(t)

	storyOn(t, root, "checkout", "-q", "-b", "development")
	tree := storyTreeFor(t, root)
	t.Logf("the tree holds %d files of %v", tree.Files(), tree.Languages())

	first := storyWorktree(t, root, 1)
	second := storyWorktree(t, root, 2)

	serviceAt(t, root)

	storyWrites(t, first, "shop/goods/invoice.py", `from shop.goods.rebuild import RebuildKind


class InvoiceKind(RebuildKind):
    number = 1
`)

	answer, served := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: first}, func(string) {})
	if !served {
		t.Fatalf("FIVE: the first worker's hand-in was DECLINED, so it reads the whole tree in its own process: %s",
			answer.Because)
	}
	if !strings.Contains(answer.Out, "invoice.py") {
		t.Fatalf("FIVE: the served hand-in does not name the worker's own file:\n%s%s", answer.Out, answer.Err)
	}

	storyOn(t, root, "checkout", "-q", "-b", "the-main-agents-own-work")

	storyWrites(t, second, "shop/goods/refund.py", `from shop.goods.rebuild import RebuildKind


class RefundKind(RebuildKind):
    number = 2
`)

	answer, served = warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: second}, func(string) {})
	if !served {
		t.Fatalf("SEVEN: the second worker's hand-in was declined after the main checkout moved: %s", answer.Because)
	}
	if !strings.Contains(answer.Out, "refund.py") {
		t.Fatalf("SEVEN: the served hand-in does not name that worker's own file:\n%s%s", answer.Out, answer.Err)
	}

	storyOn(t, root, "checkout", "-q", "development")

	answer, served = warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: root}, func(string) {})
	if !served {
		t.Fatalf("NINE: the project's own judging was declined back on its own branch: %s", answer.Because)
	}
	for _, theirs := range []string{"invoice.py", "refund.py"} {
		if strings.Contains(answer.Out, theirs) {
			t.Errorf("NINE: the project's own judging reports %s, which only a worker has:\n%s", theirs, answer.Out)
		}
	}
}

func storyProject(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Setenv(pluginData, t.TempDir())
	t.Setenv("JOURNAL_ROOT", root)

	written := map[string]string{
		".commandments/config.json": `{"paths": ["shop/goods", "shop/tills", "tools/hook"],
 "detectors": ["ThingByItsKindDetector"]}`,
		".commandments/custom/ThingByItsKindDetector.json": `{"engine": "python",
 "sin": {"name": "thing-by-its-kind", "description": "a class the chain of its parents says is a kind", "skill": "python/absence"},
 "find": {"select": "type-declaration", "where": [{"extendsAny": "Kind"}]}}`,
		"shop/__init__.py":       "",
		"tools/__init__.py":      "",
		"shop/goods/__init__.py": "",
		"shop/goods/kinds.py":    "class Kind:\n    number = 0\n",
		"shop/goods/rebuild.py":  "from shop.goods.kinds import Kind\n\n\nclass RebuildKind(Kind):\n    statements: list[str] = []\n",
		"shop/tills/__init__.py": "",
		"shop/tills/till.py":     "class Till:\n    number = 0\n",
		"tools/hook/__init__.py": "",
		"tools/hook/hook.py":     "class Hook:\n    number = 0\n",
		"shop/goods/Order.cs":    "namespace Shop;\n\npublic class Order {}\n",
		"shop/tills/Till.cs":     "namespace Shop;\n\npublic class Till {}\n",
	}

	for path, text := range written {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	storyOn(t, root, "init", "-q", "-b", "main")
	storyOn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "add", "-A")
	storyOn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project")

	return root
}

func storyOn(t *testing.T, root string, args ...string) {
	t.Helper()

	if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %v: %s", args, out)
	}
}

func storyWorktree(t *testing.T, root string, worker int) string {
	t.Helper()

	at := filepath.Join(root, ".journal", "worktrees", "development", fmt.Sprintf("worker-%d", worker))
	storyOn(t, root, "worktree", "add", "-q", "-b", fmt.Sprintf("journal/development/worker-%d", worker), at)

	return at
}

func storyTreeFor(t *testing.T, root string) *warm.Base {
	t.Helper()

	settings, _ := config.Load(root)

	roots, err := config.DeclaredRoots(root)
	if err != nil || len(roots) == 0 {
		t.Fatalf("the project declares no root: %v", err)
	}

	codebase, err := scan.Walk(roots, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	base := kept.Hold(project, branch, roots, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	return base
}

func storyWrites(t *testing.T, checkout, under, text string) {
	t.Helper()

	whole := filepath.Join(checkout, under)
	if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func asFloat(held any) float64 {
	number, _ := held.(float64)

	return number
}

