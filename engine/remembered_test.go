package engine_test

import (
	"crypto/sha256"
	"encoding/binary"
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

func TestTwoFilesOfTheSameContentsInOneJudgingAreAskedOnce(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, paths := treeHolding(t, "twin", "twin")
	found := memory.Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times about one file's contents", detector.asked)
	}

	files := []string{found[0].File(), found[1].File()}
	slices.Sort(files)
	slices.Sort(paths)

	if len(found) != 2 || !slices.Equal(files, paths) {
		t.Errorf("each twin should be answered under its own path, and the answers are %v", found)
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

	now = now.Add(engine.RememberedFor + time.Minute)
	judge()

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and the fingerprint had expired", detector.asked)
	}
}

func TestAFileIsCountedOnceJudgedAndThenOnceRemembered(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}
	before := engine.Counted()

	tree, _ := treeHolding(t, "kept")
	memory.Find(tree, detector.find)
	tree, _ = treeHolding(t, "kept")
	memory.Find(tree, detector.find)

	if did := engine.Since(before); did.Judged != 1 || did.Remembered != 1 {
		t.Errorf("one file judged and then known counted %d judged and %d remembered", did.Judged, did.Remembered)
	}

	tree, _ = treeHolding(t, "changed")
	memory.Find(tree, detector.find)

	if did := engine.Since(before); did.Judged != 2 || did.Remembered != 1 {
		t.Errorf("a changed file left %d judged and %d remembered", did.Judged, did.Remembered)
	}
}

func TestARestoredFindingWhoseNodeIsGoneIsJudgedAgain(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, _ := treeHolding(t, "kept")
	memory.Find(tree, detector.find)

	verdicts := memory.Verdicts()
	verdicts[0].Found = []int{1 << 30}
	restored := engine.RememberingFrom(time.Now, verdicts)

	tree, paths := treeHolding(t, "kept")
	again := restored.Find(tree, detector.find)

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and the remembered node is not in the tree", detector.asked)
	}

	if len(again) != 1 || again[0].File() != paths[0] {
		t.Errorf("the finding after judging again is %v", again)
	}
}

func TestARestoredMemoryAnswersAKnownFileWithoutTheDetector(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{finds: true}

	tree, paths := treeHolding(t, "kept")
	memory.Find(tree, detector.find)

	restored := engine.RememberingFrom(time.Now, memory.Verdicts())

	tree, _ = treeHolding(t, "kept")
	again := restored.Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and the restored memory knew the file", detector.asked)
	}

	if len(again) != 1 || again[0].File() != paths[0] {
		t.Errorf("the restored finding is %v", again)
	}
}

func TestAFingerprintIsTheSameInEveryProcess(t *testing.T) {
	memory := engine.Remembering(time.Now)

	tree, _ := treeHolding(t, "stable")
	memory.Find(tree, (&asking{}).find)

	sum := sha256.Sum256([]byte("stable" + strings.Repeat(" ", 1<<20)))
	want := binary.LittleEndian.Uint64(sum[:8])

	if verdicts := memory.Verdicts(); len(verdicts) != 1 || verdicts[0].Fingerprint != want {
		t.Errorf("the fingerprint is %v and contents alone make %d", verdicts, want)
	}
}

func TestAHitRenewsAnEntry(t *testing.T) {
	now := time.Now()
	memory := engine.Remembering(func() time.Time { return now })
	detector := &asking{}

	judge := func() {
		tree, _ := treeHolding(t, "used")
		memory.Find(tree, detector.find)
	}

	judge()
	now = now.Add(engine.RememberedFor - time.Hour)
	judge()
	now = now.Add(engine.RememberedFor - time.Hour)
	judge()

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and each hit renewed the entry", detector.asked)
	}

	if verdicts := memory.Verdicts(); len(verdicts) != 1 || verdicts[0].Used != now.UnixNano() {
		t.Errorf("the verdict after the hit is %v, used at %d", verdicts, now.UnixNano())
	}
}

func TestAHitAndAJudgingMarkTheMemoryChanged(t *testing.T) {
	memory := engine.Remembering(time.Now)
	detector := &asking{}

	if memory.Changed() {
		t.Error("a memory that was never used has nothing to save")
	}

	tree, _ := treeHolding(t, "changing")
	memory.Find(tree, detector.find)

	if !memory.Changed() {
		t.Error("a judging is something to save")
	}

	memory.Verdicts()

	if memory.Changed() {
		t.Error("what was just taken for saving has not changed since")
	}

	tree, _ = treeHolding(t, "changing")
	memory.Find(tree, detector.find)

	if !memory.Changed() {
		t.Error("a hit renews its entry, and that is something to save")
	}
}

func TestARestoredEntryPastTwelveHoursIsGone(t *testing.T) {
	now := time.Now()
	clock := func() time.Time { return now }
	memory := engine.Remembering(clock)
	detector := &asking{}

	tree, _ := treeHolding(t, "aged")
	memory.Find(tree, detector.find)

	saved := memory.Verdicts()
	now = now.Add(engine.RememberedFor + time.Minute)
	restored := engine.RememberingFrom(clock, saved)

	if !restored.Changed() {
		t.Error("an expired entry dropped on load has to leave the file too")
	}

	if verdicts := restored.Verdicts(); len(verdicts) != 0 {
		t.Errorf("the expired entry was restored: %v", verdicts)
	}

	tree, _ = treeHolding(t, "aged")
	restored.Find(tree, detector.find)

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and the entry had expired", detector.asked)
	}
}

func TestAnExpiredEntryLeavesTheVerdictsWithoutAHit(t *testing.T) {
	now := time.Now()
	memory := engine.Remembering(func() time.Time { return now })

	tree, _ := treeHolding(t, "unused")
	memory.Find(tree, (&asking{}).find)
	memory.Verdicts()

	now = now.Add(engine.RememberedFor + time.Minute)

	if !memory.Changed() {
		t.Error("an expired entry has to be deleted from the file, with no hit to prompt it")
	}

	if verdicts := memory.Verdicts(); len(verdicts) != 0 {
		t.Errorf("the expired entry is still kept: %v", verdicts)
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
