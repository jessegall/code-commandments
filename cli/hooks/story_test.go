package hooks

import (
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

// TestTheStoryOfAProjectWithAMainAgentAndItsWorkers is one run of the whole thing, told as the story it really
// is, so a break says which part of it stopped being true rather than which function changed.
//
// Every beat below was a bug on a consumer's machine in one evening, and each was found by them and not by this
// suite, because what the suite had was a convenient arrangement rather than the journal's own: a worker's edit
// dropped without a word, a file counted as changed for being touched, a decline no one could read, an edit's
// own line numbers thrown away on a clock skew, every file of a worktree mapped to a path the tree does not
// hold, a file of a language the tree holds nothing of counted for ever, and a worker read six thousand files
// on every hand-in because the tree was looked up by whatever branch the main checkout stood on at that moment.
//
// So this test is the story and not the parts: if it passes, an agent and its workers are answered from one tree.
func TestTheStoryOfAProjectWithAMainAgentAndItsWorkers(t *testing.T) {
	// ONE. A project as a real one is: source under several NESTED declared paths, and files of a language the
	// tree holds nothing of, since its bridge is a service of its own.
	root := storyProject(t)

	// TWO. The main agent works on `development`, and the tree is read for the branch that checkout stands on.
	// Everything after this answers from it or says why it cannot.
	storyOn(t, root, "checkout", "-q", "-b", "development")
	tree := storyTreeFor(t, root)
	t.Logf("the tree holds %d files of %v", tree.Files(), tree.Languages())

	// THREE. Two workers, in worktrees where the journal really keeps them: under an environment's own folder,
	// each on a branch of its own cut from development.
	first := storyWorktree(t, root, 1)
	second := storyWorktree(t, root, 2)

	service := serviceAt(t, root)

	// FOUR. The first worker writes a sin whose CHAIN runs through two other files. No one file can answer for
	// it, so an answer that names it is proof the edit was judged against the whole program.
	storyWrites(t, first, "shop/goods/invoice.py", `from shop.goods.rebuild import RebuildKind


class InvoiceKind(RebuildKind):
    number = 1
`)

	told := storyEdited(t, service, root, first, 1, "shop/goods/invoice.py")
	if !strings.Contains(told, "thing-by-its-kind") {
		t.Fatalf("FOUR: the first worker's edit was not told the sin that only the whole program can find, so it "+
			"was judged on its own file alone:\n%s", told)
	}

	// FIVE. That worker hands in. It must be SERVED from the tree and not read the tree itself: a declined
	// judging answers correctly and costs 25 to 55 seconds, and nothing about the answer says it happened.
	answer, served := warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: first})
	if !served {
		t.Fatalf("FIVE: the first worker's hand-in was DECLINED, so it reads the whole tree in its own process: %s",
			answer.Because)
	}
	if !strings.Contains(answer.Out, "invoice.py") {
		t.Fatalf("FIVE: the served hand-in does not name the worker's own file:\n%s%s", answer.Out, answer.Err)
	}

	// SIX. The main agent moves to a branch of its own, which a main agent does as a matter of course. Nothing
	// about the workers changed, and their tree must not vanish because somebody else switched: a worker answers
	// from the tree of the checkout its own main agent is on.
	storyOn(t, root, "checkout", "-q", "-b", "the-main-agents-own-work")

	storyWrites(t, second, "shop/goods/refund.py", `from shop.goods.rebuild import RebuildKind


class RefundKind(RebuildKind):
    number = 2
`)

	told = storyEdited(t, service, root, second, 2, "shop/goods/refund.py")
	if !strings.Contains(told, "thing-by-its-kind") {
		t.Fatalf("SIX: the second worker's edit lost the whole program when the MAIN checkout switched branch, "+
			"which is nothing to do with that worker:\n%s", told)
	}

	// SEVEN. And its hand-in is still served, from the tree read at the branch it was cut from.
	answer, served = warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: second})
	if !served {
		t.Fatalf("SEVEN: the second worker's hand-in was declined after the main checkout moved: %s", answer.Because)
	}
	if !strings.Contains(answer.Out, "refund.py") {
		t.Fatalf("SEVEN: the served hand-in does not name that worker's own file:\n%s%s", answer.Out, answer.Err)
	}

	// EIGHT. What each worker is TOLD is about its own files and never another's: the program holds one copy of
	// each source, the checkout's, and a worker told about a file in a tree it does not have reads as nonsense.
	//
	// The cards are not held to this and must not be: every checkout here works in one environment, which keeps
	// ONE record of what has been announced, so a card settling a file that became clean legitimately names
	// whatever file of that record it is about. It is the whisper the agent READS that is its own.
	if whisper := storyWhisper(t, told); strings.Contains(whisper, "invoice.py") {
		t.Errorf("EIGHT: the second worker is told about the first worker's file:\n%s", whisper)
	}

	// NINE. The main agent finishes its own work and comes back to the branch it shares with its workers, and
	// its own judging is served again from the tree read there.
	storyOn(t, root, "checkout", "-q", "development")

	answer, served = warm.Asked(root, warm.Request{Judge: []string{"--no-checklist"}, In: root})
	if !served {
		t.Fatalf("NINE: the project's own judging was declined back on its own branch: %s", answer.Because)
	}
	for _, theirs := range []string{"invoice.py", "refund.py"} {
		if strings.Contains(answer.Out, theirs) {
			t.Errorf("NINE: the project's own judging reports %s, which only a worker has:\n%s", theirs, answer.Out)
		}
	}
}

// storyProject is the project the story happens in: nested declared paths, a rule no single file can answer, and
// files of a language the tree holds nothing of.
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
		"shop/__init__.py":        "",
		"tools/__init__.py":       "",
		"shop/goods/__init__.py":  "",
		"shop/goods/kinds.py":     "class Kind:\n    number = 0\n",
		"shop/goods/rebuild.py":   "from shop.goods.kinds import Kind\n\n\nclass RebuildKind(Kind):\n    statements: list[str] = []\n",
		"shop/tills/__init__.py":  "",
		"shop/tills/till.py":      "class Till:\n    number = 0\n",
		"tools/hook/__init__.py":  "",
		"tools/hook/hook.py":      "class Hook:\n    number = 0\n",
		"shop/goods/Order.cs":     "namespace Shop;\n\npublic class Order {}\n",
		"shop/tills/Till.cs":      "namespace Shop;\n\npublic class Till {}\n",
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

// storyOn runs git in the project and fails the test where git does.
func storyOn(t *testing.T, root string, args ...string) {
	t.Helper()

	if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %v: %s", args, out)
	}
}

// storyWorktree is a worker's tree where the journal keeps one: under an environment's folder, on its own branch.
func storyWorktree(t *testing.T, root string, worker int) string {
	t.Helper()

	at := filepath.Join(root, ".journal", "worktrees", "development", fmt.Sprintf("worker-%d", worker))
	storyOn(t, root, "worktree", "add", "-q", "-b", fmt.Sprintf("journal/development/worker-%d", worker), at)

	return at
}

// storyTreeFor is the tree the main agent's session has read, over the paths the project declares.
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

// storyWrites is one worker writing a file in its own checkout.
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

// storyEdited tells the plugin about that edit the way the journal does, on the socket, and answers what the
// worker was told: the answer goes out before the judging, so what it was told is what reached the queue.
func storyEdited(t *testing.T, service, root, checkout string, worker int, under string) string {
	t.Helper()

	sendEvent(t, service, map[string]any{
		"v": 1, "event": "file_edited", "session": fmt.Sprintf("s%d", worker), "environment": "development",
		"agent": worker, "cwd": checkout,
		"data": map[string]any{"path": under, "kind": "create", "added": 5, "removed": 0, "cwd": checkout},
	})

	// The queue the journal drains, waited for: the service answers the connection at once and judges afterwards.
	for until := time.Now().Add(2 * time.Minute); time.Now().Before(until); time.Sleep(50 * time.Millisecond) {
		if said := storyQueued(t, worker); said != "" {
			return said
		}
	}

	return ""
}

// storyQueued is everything the queue holds for that worker, and empty while it holds nothing.
func storyQueued(t *testing.T, worker int) string {
	t.Helper()

	folder := filepath.Join(os.Getenv(pluginData), queueFolder)
	entries, err := os.ReadDir(folder)
	if err != nil {
		return ""
	}

	var said strings.Builder
	for _, entry := range entries {
		if !strings.HasSuffix(entry.Name(), ".json") {
			continue
		}

		raw, err := os.ReadFile(filepath.Join(folder, entry.Name()))
		if err != nil {
			continue
		}

		var answer map[string]any
		if json.Unmarshal(raw, &answer) != nil {
			continue
		}

		if int(asFloat(answer["agent"])) != worker {
			continue
		}

		os.Remove(filepath.Join(folder, entry.Name()))
		said.Write(raw)
	}

	return said.String()
}

// asFloat is a JSON number, and nought for anything else.
func asFloat(held any) float64 {
	number, _ := held.(float64)

	return number
}

// storyWhisper is the words an agent READS out of what reached its queue, which is its own business, as against
// the cards, which are the environment's: one environment keeps one record of what has been announced, so a card
// may name any file of it.
func storyWhisper(t *testing.T, told string) string {
	t.Helper()

	var said strings.Builder
	for _, line := range strings.Split(told, "}{") {
		var answer map[string]any
		if json.Unmarshal([]byte(line), &answer) != nil {
			continue
		}

		said.WriteString(asText(answer["tell"]))
	}

	if said.Len() == 0 {
		return asText(told)
	}

	return said.String()
}
