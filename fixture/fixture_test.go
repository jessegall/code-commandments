package fixture_test

import (
	"fmt"
	"os"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/sins"
)

// LoopedNew is the toy sin: an object constructed inside a loop.
type LoopedNew struct{}

func (LoopedNew) Definition() sins.Definition {
	return sins.Definition{Name: "looped-new"}
}

// LoopedNewDetector finds the toy sin, and is right.
type LoopedNewDetector struct{}

func (LoopedNewDetector) Sin() sins.Sin { return LoopedNew{} }

func (LoopedNewDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Where(engine.Match.IsWithinLoop).
		Get()
}

// TooNarrowDetector looks only inside classes, so it misses the marked function.
type TooNarrowDetector struct{ LoopedNewDetector }

func (TooNarrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Where(engine.Match.IsWithinLoop).
		Where(func(m engine.Match) bool { return m.EnclosingType().Exists() }).
		Get()
}

// TooWideDetector flags every construction, so it flags the righteous twin.
type TooWideDetector struct{ LoopedNewDetector }

func (TooWideDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Get()
}

// toy is the toy fixture: three PHP files emitted as one generic tree.
func toy(t *testing.T) *engine.Codebase {
	t.Helper()
	file, err := os.Open("testdata/toy.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer file.Close()
	stream, err := contract.ReadAll(file)
	if err != nil {
		t.Fatal(err)
	}

	return engine.New(func(path string) ([]byte, error) {
		return os.ReadFile("testdata" + path)
	}, stream)
}

func TestTheMarkersAreReadFromAttributesAndComments(t *testing.T) {
	var read []string
	for _, marker := range fixture.Markers(toy(t)) {
		read = append(read, fmt.Sprintf("%s %s %s", marker.Tag, marker.Name, marker.Location))
	}

	want := []string{
		"sin LoopedNew /toy/Cart.php:12",
		"righteous LoopedNew /toy/Cart.php:24",
		"fixed LoopedNew /toy/Cart.php:30",
		"sin LoopedNew /toy/Ledger.php:8",
		"sin LoopedNew /toy/batches.php:11",
	}
	if !slices.Equal(read, want) {
		t.Fatalf("got\n%s", strings.Join(read, "\n"))
	}
}

func TestTheHarnessPassesADetectorThatFlagsExactlyItsMarks(t *testing.T) {
	fixture.Prove(t, toy(t), LoopedNewDetector{})
}

func TestTheHarnessFailsADetectorThatMissesAMark(t *testing.T) {
	result := fixture.Verify(toy(t), TooNarrowDetector{})[0]

	if result.Passed() || !slices.Equal(result.Missed, []string{"/toy/batches.php:11"}) || len(result.Unexpected) != 0 {
		t.Fatalf("got %+v", result)
	}
	if !prove(toy(t), TooNarrowDetector{}).Failed() {
		t.Fatal("Prove passed a detector that misses a mark")
	}
}

func TestTheHarnessFailsADetectorThatFlagsTheRighteousTwin(t *testing.T) {
	result := fixture.Verify(toy(t), TooWideDetector{})[0]

	if result.Passed() || len(result.Missed) != 0 || !slices.Equal(result.Unexpected, []string{"/toy/Cart.php:27"}) {
		t.Fatalf("got %+v", result)
	}
	if !prove(toy(t), TooWideDetector{}).Failed() {
		t.Fatal("Prove passed a detector that flags unmarked code")
	}
}

func TestTheHarnessCountsDiverseScenarios(t *testing.T) {
	scenarios, err := fixture.Scenarios(toy(t), TooNarrowDetector{})
	if err != nil {
		t.Fatal(err)
	}

	if largest := fixture.LargestDiverseGroup(scenarios); largest != 2 {
		t.Fatalf("two findings in two files make a group of 2, got %d", largest)
	}
}

func TestSimilarityIsPHPsSimilarText(t *testing.T) {
	// Each want is PHP's own answer: similar_text($a, $b, $percent).
	for _, pair := range []struct {
		a, b string
		want float64
	}{
		{"World", "Word", 88.88888888888889},
		{"Hello World", "Hello  World", 100},
		{"return new Item($row);", "return Item::many($rows);", 76.59574468085107},
		{"abc", "xyz", 0},
	} {
		if got := fixture.Similarity(pair.a, pair.b); got != pair.want {
			t.Errorf("%q ~ %q: got %v, want %v", pair.a, pair.b, got, pair.want)
		}
	}
}

// recorder is a test that records a failure instead of stopping.
type recorder struct {
	testing.TB
	failed bool
}

func (r *recorder) Helper()               {}
func (r *recorder) Error(...any)          { r.failed = true }
func (r *recorder) Errorf(string, ...any) { r.failed = true }
func (r *recorder) Failed() bool          { return r.failed }

// prove runs Prove on a recorder, so a test can see it fail.
func prove(codebase *engine.Codebase, detector detectors.Detector) *recorder {
	proven := &recorder{}
	fixture.Prove(proven, codebase, detector)

	return proven
}

func TestMarkupCommentsMarkTheElementTheyLead(t *testing.T) {
	file, err := os.Open("../contract/samples/vue.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer file.Close()
	stream, err := contract.ReadAll(file)
	if err != nil {
		t.Fatal(err)
	}
	var marked []string
	for _, marker := range fixture.Markers(engine.New(os.ReadFile, stream)) {
		if marker.Tag == fixture.Sinful {
			marked = append(marked, marker.Name+" "+marker.Location)
		}
	}

	stock := "/fixtures/frontend/components/StockIndicator.vue"
	want := []string{"ControlFlowOnElement " + stock + ":8", "ControlFlowOnElement " + stock + ":10", "ControlFlowOnElement " + stock + ":12"}
	if !slices.Equal(marked, want) {
		t.Fatalf("got %v", marked)
	}
}
