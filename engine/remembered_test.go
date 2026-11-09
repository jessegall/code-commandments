package engine_test

import (
	"os"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func treeHolding(t *testing.T, contents ...string) (*engine.Codebase, []string) {
	t.Helper()

	sampled, err := os.Open("../contract/samples/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer sampled.Close()

	stream, err := contract.ReadAll(sampled)
	if err != nil {
		t.Fatal(err)
	}

	holds := map[string]string{}
	codebase := engine.New(func(path string) ([]byte, error) {
		return []byte(holds[path] + strings.Repeat(" ", 1<<20)), nil
	}, stream)

	var paths []string
	for _, match := range codebase.WhereFunction().Get() {
		if len(paths) < len(contents) && !slices.Contains(paths, match.File()) {
			holds[match.File()] = contents[len(paths)]
			paths = append(paths, match.File())
		}
	}

	return codebase.Reporting(paths...), paths
}

// asking is a detector's whole verdict, counting the files it was really asked about.
type asking struct {
	asked int
	finds bool
}

func (a *asking) find(codebase *engine.Codebase) []engine.Match {
	var roots []engine.Match
	for _, match := range codebase.WhereFunction().Get() {
		if !slices.ContainsFunc(roots, func(root engine.Match) bool { return root.File() == match.File() }) {
			roots = append(roots, match.Root())
		}
	}

	a.asked += len(roots)
	if !a.finds {
		return nil
	}

	return roots
}

func TestAFileWithFindingsIsAnsweredFromMemory(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, paths := treeHolding(t, "same")
	first := memory.Find(tree, detector.find)

	tree, _ = treeHolding(t, "same")
	again := memory.Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and it knew the file the second time", detector.asked)
	}

	if len(first) != 1 || len(again) != 1 || again[0].File() != paths[0] {
		t.Errorf("the remembered finding is %v, from %v", again, first)
	}
}

func TestACleanFileIsAnsweredFromMemory(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}

	tree, _ := treeHolding(t, "clean")
	memory.Find(tree, detector.find)

	tree, _ = treeHolding(t, "clean")
	if again := memory.Find(tree, detector.find); len(again) != 0 || detector.asked != 1 {
		t.Errorf("a clean file answered %v after %d askings", again, detector.asked)
	}
}

func TestChangedContentsAreJudgedAgain(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}

	for _, text := range []string{"before", "after"} {
		tree, _ := treeHolding(t, text)
		memory.Find(tree, detector.find)
	}

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times about two different contents", detector.asked)
	}
}

func TestContentsReturningToAnEarlierStateAreKnown(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}

	for _, text := range []string{"one", "two", "one"} {
		tree, _ := treeHolding(t, text)
		memory.Find(tree, detector.find)
	}

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times over two different contents", detector.asked)
	}
}

func TestTheSameContentsAtAnotherPathAreTheSameFile(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, paths := treeHolding(t, "shared", "shared")
	found := memory.Find(tree.Reporting(paths[0]), detector.find)
	again := memory.Find(tree.Reporting(paths[1]), detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times about the same contents", detector.asked)
	}

	if len(found) != 1 || len(again) != 1 || again[0].File() != paths[1] {
		t.Errorf("the finding should stand in the file that was asked about, and is %v", again)
	}
}

func TestAFingerprintIsForgottenAfterTwelveHours(t *testing.T) {
	now := time.Now()
	memory := engine.Remembering(func() time.Time { return now })
	detector := &asking{}

	judge := func() {
		tree, _ := treeHolding(t, "kept")
		memory.Find(tree, detector.find)
	}

	judge()
	now = now.Add(engine.RememberedFor - time.Minute)
	judge()

	if detector.asked != 1 {
		t.Fatalf("the detector was asked %d times inside the twelve hours", detector.asked)
	}

	now = now.Add(2 * time.Minute)
	judge()

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and the fingerprint had expired", detector.asked)
	}
}

func TestOnlyTheFilesItDoesNotKnowAreAsked(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, paths := treeHolding(t, "known", "new")
	memory.Find(tree.Reporting(paths[0]), detector.find)
	both := memory.Find(tree, detector.find)

	if detector.asked != 2 || len(both) != 2 {
		t.Errorf("asked %d times and found %d, over one known file and one new", detector.asked, len(both))
	}
}
