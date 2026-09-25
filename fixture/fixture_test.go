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

func (LoopedNew) Definition() sins.Definition { return sins.Definition{Name: "looped-new"} }

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

// StaticCallDetector flags static calls, so it flags the #[Fixed] resolution.
type StaticCallDetector struct{ LoopedNewDetector }

func (StaticCallDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereKind("Expr_StaticCall").
		Get()
}

// Shouting is a toy sin whose two marked scenarios are copies of each other.
type Shouting struct{}

func (Shouting) Definition() sins.Definition { return sins.Definition{Name: "shouting"} }

// ShoutingDetector flags exactly its marks, but they are not diverse.
type ShoutingDetector struct{}

func (ShoutingDetector) Sin() sins.Sin { return Shouting{} }

func (ShoutingDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereCall().
		Where(func(m engine.Match) bool { return m.Child("name").Name() == "strtoupper" }).
		Get()
}

// Doubled is a toy sin marked twice, with two fixes, in one file.
type Doubled struct{}

func (Doubled) Definition() sins.Definition { return sins.Definition{Name: "doubled"} }

type DoubledDetector struct{}

func (DoubledDetector) Sin() sins.Sin { return Doubled{} }

func (DoubledDetector) Find(*engine.Codebase) []engine.Match { return nil }

// known is the toy catalog a #[Fixed] must name.
var known = []detectors.Detector{LoopedNewDetector{}, ShoutingDetector{}, DoubledDetector{}}

// toy is the toy fixture: six PHP files emitted as one generic tree.
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

// proving is the toy fixture proving the detectors, against the toy catalog.
func proving(t *testing.T, proven ...detectors.Detector) fixture.Fixture {
	return fixture.Fixture{Codebase: toy(t), Detectors: proven, Known: known}
}

func TestTheMarkersAreReadFromAttributesAndComments(t *testing.T) {
	var read []string
	for _, marker := range fixture.Markers(toy(t)) {
		read = append(read, fmt.Sprintf("%s %s %s %s", marker.Tag, marker.Name, marker.Class, marker.Location))
	}

	want := []string{
		`sin LoopedNew Toy\Cart /toy/Cart.php:12`,
		`righteous LoopedNew Toy\Cart /toy/Cart.php:24`,
		`fixed LoopedNew Toy\Cart /toy/Cart.php:30`,
		`sin Shouting Toy\Holler /toy/Holler.php:11`,
		`righteous Shouting Toy\Holler /toy/Holler.php:17`,
		`sin LoopedNew Toy\Ledger /toy/Ledger.php:8`,
		`sin Shouting Toy\Shout /toy/Shout.php:11`,
		`righteous Shouting Toy\Shout /toy/Shout.php:17`,
		`sin Doubled Toy\First /toy/Twice.php:11`,
		`fixed Doubled Toy\First /toy/Twice.php:17`,
		`sin Doubled Toy\Second /toy/Twice.php:26`,
		`fixed Doubled Toy\Second /toy/Twice.php:32`,
		`sin LoopedNew (file) /toy/batches.php:11`,
	}
	if !slices.Equal(read, want) {
		t.Fatalf("got\n%s", strings.Join(read, "\n"))
	}
}

func TestTheHarnessPassesADetectorThatFlagsExactlyItsMarks(t *testing.T) {
	proving(t, LoopedNewDetector{}).Prove(t)
}

func TestTheHarnessFailsADetectorThatMissesAMark(t *testing.T) {
	result := proving(t, TooNarrowDetector{}).Verify()[0]

	if !slices.Equal(result.Missed, []string{"/toy/batches.php:11"}) || !passedBut(result, "Missed") {
		t.Fatalf("got %+v", result)
	}
	if failures := prove(proving(t, TooNarrowDetector{})); !contains(failures, "missed marked sins") {
		t.Fatalf("got %v", failures)
	}
}

func TestTheHarnessFailsADetectorThatFlagsTheRighteousTwinForThatReason(t *testing.T) {
	result := proving(t, TooWideDetector{}).Verify()[0]

	if !slices.Equal(result.FlaggedRighteous, []string{"/toy/Cart.php:27"}) || !passedBut(result, "FlaggedRighteous") {
		t.Fatalf("got %+v", result)
	}
	if failures := prove(proving(t, TooWideDetector{})); !contains(failures, "flagged its righteous twin") || contains(failures, "unmarked") {
		t.Fatalf("got %v", failures)
	}
}

func TestTheHarnessFailsADetectorThatFlagsItsOwnFix(t *testing.T) {
	result := proving(t, StaticCallDetector{}).Verify()[0]

	if !slices.Equal(result.FlaggedFixed, []string{"/toy/Cart.php:33"}) {
		t.Fatalf("got %+v", result)
	}
	if failures := prove(proving(t, StaticCallDetector{})); !contains(failures, "flagged its own #[Fixed] resolution") {
		t.Fatalf("got %v", failures)
	}
}

func TestTheHarnessFailsExactMarksWithTooFewDiverseScenarios(t *testing.T) {
	shouting := proving(t, ShoutingDetector{})

	if result := shouting.Verify()[0]; !result.Passed() {
		t.Fatalf("the detector flags exactly its marks, got %+v", result)
	}
	if failures := prove(shouting); len(failures) != 1 || !contains(failures, "mutually-DIVERSE") {
		t.Fatalf("diversity is the one failure, got %v", failures)
	}
}

func TestAResolverDecidesHowADetectorsFindingsRead(t *testing.T) {
	shouting := proving(t, ShoutingDetector{})
	shouting.Resolver = func(codebase *engine.Codebase, detector detectors.Detector) ([]fixture.Scenario, error) {
		if _, ok := detector.(ShoutingDetector); !ok {
			return fixture.ScopeScenarios(codebase, detector)
		}

		return []fixture.Scenario{{File: "a", Source: "one route"}, {File: "b", Source: "a second way"}, {File: "c", Source: "third path!"}}, nil
	}

	if failures := prove(shouting); len(failures) != 0 {
		t.Fatalf("the resolver's three diverse scenarios pass, got %v", failures)
	}
}

func TestEveryDetectorNeedsARighteousTwin(t *testing.T) {
	if without := proving(t, LoopedNewDetector{}, DoubledDetector{}).WithoutRighteous(); !slices.Equal(without, []string{"DoubledDetector"}) {
		t.Fatalf("got %v", without)
	}
}

func TestAResolutionNamesASinTheCatalogKnows(t *testing.T) {
	looped := fixture.Fixture{Codebase: toy(t), Detectors: []detectors.Detector{LoopedNewDetector{}}, Known: []detectors.Detector{LoopedNewDetector{}}}

	want := []string{
		"#[Fixed(Doubled)] at /toy/Twice.php:17 names nothing in the catalog",
		"#[Fixed(Doubled)] at /toy/Twice.php:32 names nothing in the catalog",
	}
	if unknown := looped.UnknownResolutions(); !slices.Equal(unknown, want) {
		t.Fatalf("got %v", unknown)
	}
	if unknown := proving(t, LoopedNewDetector{}).UnknownResolutions(); len(unknown) != 0 {
		t.Fatalf("the toy catalog knows every resolution, got %v", unknown)
	}
}

func TestAFileHoldsAtMostOneScenarioPerSin(t *testing.T) {
	if ambiguous := proving(t, LoopedNewDetector{}, DoubledDetector{}).Ambiguous(); !slices.Equal(ambiguous, []string{"DoubledDetector has two scenarios in /toy/Twice.php"}) {
		t.Fatalf("got %v", ambiguous)
	}
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

// passedBut says whether the result holds nothing outside the one list named.
func passedBut(result fixture.Result, list string) bool {
	lists := map[string]int{"Missed": len(result.Missed), "Unexpected": len(result.Unexpected), "FlaggedRighteous": len(result.FlaggedRighteous), "FlaggedFixed": len(result.FlaggedFixed)}
	for name, size := range lists {
		if name != list && size != 0 {
			return false
		}
	}

	return true
}

// contains says whether any failure holds the text.
func contains(failures []string, text string) bool {
	return slices.ContainsFunc(failures, func(failure string) bool { return strings.Contains(failure, text) })
}

// recorder is a test that records its failures instead of stopping.
type recorder struct {
	testing.TB
	failures []string
}

func (r *recorder) Helper()           {}
func (r *recorder) Error(args ...any) { r.failures = append(r.failures, fmt.Sprint(args...)) }
func (r *recorder) Errorf(format string, args ...any) {
	r.failures = append(r.failures, fmt.Sprintf(format, args...))
}

// prove runs Prove on a recorder and answers what it failed for.
func prove(proving fixture.Fixture) []string {
	proven := &recorder{}
	proving.Prove(proven)

	return proven.failures
}

func TestLikenessReadsTwoPiecesOfCodeAlikeInEitherOrder(t *testing.T) {
	a, b := "bafoobar", "barfoo"
	if fixture.Similarity(a, b) == fixture.Similarity(b, a) {
		t.Fatal("the sample no longer reads differently in the two orders; pick one that does")
	}
	if fixture.Likeness(a, b) != fixture.Likeness(b, a) || fixture.Likeness(a, b) != min(fixture.Similarity(a, b), fixture.Similarity(b, a)) {
		t.Error("likeness depends on which piece is read first")
	}
}
