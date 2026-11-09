package detectors_test

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const savedDetector = "php\x00SomeRule\x00definition"

func aTreeHolding(t *testing.T, content string) (*engine.Codebase, string) {
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

	var path string
	codebase := engine.New(func(asked string) ([]byte, error) {
		if asked == path {
			return []byte(content + strings.Repeat(" ", 1<<20)), nil
		}

		return []byte(strings.Repeat(" ", 1<<20)), nil
	}, stream)

	path = codebase.WhereFunction().Get()[0].File()

	return codebase.Reporting(path), path
}

// countedDetector answers with one finding per file and counts the files it was asked about.
type countedDetector struct {
	asked int
}

func (d *countedDetector) find(codebase *engine.Codebase) []engine.Match {
	var roots []engine.Match
	for _, match := range codebase.WhereFunction().Get() {
		if !slices.ContainsFunc(roots, func(root engine.Match) bool { return root.File() == match.File() }) {
			roots = append(roots, match.Root())
		}
	}

	d.asked += len(roots)

	return roots
}

// fileUnderTest is a memory file in a temporary folder, its clock and the warnings it gave.
type fileUnderTest struct {
	now      time.Time
	path     string
	warnings []string
}

func aFileUnderTest(t *testing.T) *fileUnderTest {
	return &fileUnderTest{now: time.Now(), path: filepath.Join(t.TempDir(), "memories")}
}

func (f *fileUnderTest) clock() time.Time {
	return f.now
}

func (f *fileUnderTest) warn(lines ...string) {
	f.warnings = append(f.warnings, lines...)
}

func (f *fileUnderTest) of(build string) detectors.MemoryFile {
	return detectors.MemoryFile{Memories: detectors.NewMemories(f.clock), Path: f.path, Build: build, Warn: f.warn}
}

func (f *fileUnderTest) judgedAndSaved(t *testing.T, detector *countedDetector, content string) detectors.MemoryFile {
	t.Helper()

	file := f.of("one")
	tree, _ := aTreeHolding(t, content)
	file.Memories.Of(savedDetector).Find(tree, detector.find)
	file.Save()

	return file
}

func TestALoadedFileAnswersAKnownFileWithoutTheDetector(t *testing.T) {
	under := aFileUnderTest(t)
	detector := &countedDetector{}

	under.judgedAndSaved(t, detector, "known")

	loaded := under.of("one")
	loaded.Load()

	tree, path := aTreeHolding(t, "known")
	again := loaded.Memories.Of(savedDetector).Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and the file knew it", detector.asked)
	}

	if len(again) != 1 || again[0].File() != path {
		t.Errorf("the loaded finding is %v", again)
	}

	if len(under.warnings) != 0 {
		t.Errorf("a good file warned: %v", under.warnings)
	}
}

func TestAHitRenewsTheEntryInTheFile(t *testing.T) {
	under := aFileUnderTest(t)
	detector := &countedDetector{}

	under.judgedAndSaved(t, detector, "renewed")
	under.now = under.now.Add(engine.RememberedFor - time.Hour)

	hit := under.of("one")
	hit.Load()

	tree, _ := aTreeHolding(t, "renewed")
	hit.Memories.Of(savedDetector).Find(tree, detector.find)
	hit.Save()

	under.now = under.now.Add(engine.RememberedFor - time.Hour)

	loaded := under.of("one")
	loaded.Load()

	tree, _ = aTreeHolding(t, "renewed")
	loaded.Memories.Of(savedDetector).Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and the hit renewed the entry", detector.asked)
	}
}

func TestAnEntryPastTwelveHoursIsGoneFromTheFile(t *testing.T) {
	under := aFileUnderTest(t)
	detector := &countedDetector{}

	under.judgedAndSaved(t, detector, "expired")
	under.now = under.now.Add(engine.RememberedFor + time.Minute)

	loaded := under.of("one")
	loaded.Load()
	loaded.Save()

	raw, err := os.ReadFile(under.path)
	if err != nil {
		t.Fatal(err)
	}

	again := under.of("one")
	again.Load()

	if verdicts := again.Memories.Verdicts()[savedDetector]; len(verdicts) != 0 {
		t.Errorf("the expired entry is still in the file of %d bytes: %v", len(raw), verdicts)
	}

	tree, _ := aTreeHolding(t, "expired")
	again.Memories.Of(savedDetector).Find(tree, detector.find)

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and the entry had expired", detector.asked)
	}
}

func TestAFileFromAnotherBuildIsIgnored(t *testing.T) {
	under := aFileUnderTest(t)
	detector := &countedDetector{}

	under.judgedAndSaved(t, detector, "built")

	loaded := under.of("two")
	loaded.Load()

	tree, _ := aTreeHolding(t, "built")
	loaded.Memories.Of(savedDetector).Find(tree, detector.find)

	if detector.asked != 2 {
		t.Errorf("the detector was asked %d times, and another build wrote the file", detector.asked)
	}

	if len(under.warnings) != 0 {
		t.Errorf("another build's file is no fault: %v", under.warnings)
	}
}

func TestACorruptFileStartsEmptyWithAWarning(t *testing.T) {
	under := aFileUnderTest(t)

	if err := os.WriteFile(under.path, []byte("not a memory file"), 0o644); err != nil {
		t.Fatal(err)
	}

	loaded := under.of("one")
	loaded.Load()

	if len(under.warnings) != 1 {
		t.Errorf("a corrupt file gave the warnings %v", under.warnings)
	}

	detector := &countedDetector{}
	tree, _ := aTreeHolding(t, "fresh")
	loaded.Memories.Of(savedDetector).Find(tree, detector.find)

	if detector.asked != 1 {
		t.Errorf("the detector was asked %d times, and it should have started empty", detector.asked)
	}
}

func TestAMissingFileStartsEmptyWithoutAWarning(t *testing.T) {
	under := aFileUnderTest(t)

	under.of("one").Load()

	if len(under.warnings) != 0 {
		t.Errorf("a first start warned: %v", under.warnings)
	}
}

func TestNothingChangedWritesNothing(t *testing.T) {
	under := aFileUnderTest(t)

	file := under.judgedAndSaved(t, &countedDetector{}, "still")

	if err := os.Remove(under.path); err != nil {
		t.Fatal(err)
	}

	file.Save()

	if _, err := os.Stat(under.path); !os.IsNotExist(err) {
		t.Errorf("a save with nothing changed wrote the file: %v", err)
	}
}

func TestTheFileHoldsNoPath(t *testing.T) {
	under := aFileUnderTest(t)

	under.judgedAndSaved(t, &countedDetector{}, "pathless")

	_, path := aTreeHolding(t, "pathless")

	raw, err := os.ReadFile(under.path)
	if err != nil {
		t.Fatal(err)
	}

	if strings.Contains(string(raw), path) {
		t.Errorf("the file names the path %s", path)
	}
}
