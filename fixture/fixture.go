package fixture

import (
	"fmt"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// Fixture is a marked codebase and the detectors it proves.
type Fixture struct {
	Codebase  *engine.Codebase
	Detectors []detectors.Detector
	// Known is the catalog a #[Fixed] must name a sin of; nil means every published detector.
	Known []detectors.Detector
	// Resolver reads each detector's findings as scenarios; nil reads each as its scope.
	Resolver ScenarioResolver
}

// Result is how one detector fared against the fixture's markers.
type Result struct {
	Detector         string
	Missed           []string
	Unexpected       []string
	FlaggedRighteous []string
	FlaggedFixed     []string
}

// Passed says whether the detector flagged exactly what its markers mark.
func (r Result) Passed() bool {
	return len(r.Missed)+len(r.Unexpected)+len(r.FlaggedRighteous)+len(r.FlaggedFixed) == 0
}

// Prove fails the test unless every detector flags exactly its marked sins, leaves its righteous
// twin and its fixes alone, and fires on at least MinScenarios mutually diverse findings — and the
// fixture's own markers are sound.
func (f Fixture) Prove(t testing.TB) {
	t.Helper()
	if len(f.Detectors) == 0 {
		t.Error("no detectors were verified against the fixture")
	}
	for _, result := range f.Verify() {
		f.report(t, result)
	}
	for _, name := range f.WithoutRighteous() {
		t.Errorf("%s has no @righteous twin — add one good example of what it must leave alone", name)
	}
	for _, unknown := range f.UnknownResolutions() {
		t.Error(unknown)
	}
	for _, ambiguous := range f.Ambiguous() {
		t.Errorf("%s: a file holds two sinful classes and two resolutions for one sin, so which repair answers which is undecidable — split the scenarios into separate fixture files", ambiguous)
	}
	for _, detector := range f.Detectors {
		f.proveDiversity(t, detector)
	}
	for detector, deepest := range f.ChainDepths() {
		if deepest < MinChainFiles {
			t.Errorf("%s is a chain detector but its deepest finding crosses only %d file(s); it must follow a value through %d or more", detector, deepest, MinChainFiles)
		}
	}
	for detector, widest := range f.RecurrenceSpans() {
		if widest < MinRecurrenceFiles {
			t.Errorf("%s is a recurrence detector but its widest group touches only %d file(s); mark one recurring group across two classes or files, not twice in one", detector, widest)
		}
	}
}

// MinChainFiles is how many files a chain detector's deepest finding must cross.
const MinChainFiles = 5

// ChainDepths is, for every chain detector, how many files its deepest finding's chain crosses.
func (f Fixture) ChainDepths() map[string]int {
	depths := map[string]int{}
	for _, detector := range f.Detectors {
		chained, ok := detector.(detectors.ChainDetector)
		if !ok {
			continue
		}
		deepest := 0
		for _, finding := range detector.Find(f.Codebase) {
			files := map[string]bool{}
			for _, step := range chained.ChainPath(finding, f.Codebase) {
				if _, file, found := strings.Cut(step, "@"); found {
					files[file] = true
				}
			}
			deepest = max(deepest, len(files))
		}
		depths[catalog.Name(detector)] = deepest
	}

	return depths
}

// MinRecurrenceFiles is how many files a recurrence detector's widest group must reach.
const MinRecurrenceFiles = 2

// RecurrenceSpans is, for every recurrence detector, how many files its widest group of findings reaches.
func (f Fixture) RecurrenceSpans() map[string]int {
	spans := map[string]int{}
	for _, detector := range f.Detectors {
		recurring, ok := detector.(detectors.Grouped)
		if !ok {
			continue
		}
		groups := map[string]map[string]bool{}
		widest := 0
		for _, finding := range detector.Find(f.Codebase) {
			key, _ := recurring.GroupKey(finding)
			if groups[key] == nil {
				groups[key] = map[string]bool{}
			}
			groups[key][finding.File()] = true
			widest = max(widest, len(groups[key]))
		}
		spans[catalog.Name(detector)] = widest
	}

	return spans
}

// report fails the test for each way a detector's findings missed its markers.
func (f Fixture) report(t testing.TB, result Result) {
	t.Helper()
	if len(result.Missed) > 0 {
		t.Errorf("%s missed marked sins: %v", result.Detector, result.Missed)
	}
	if len(result.FlaggedRighteous) > 0 {
		t.Errorf("%s flagged its righteous twin, code it must leave alone: %v", result.Detector, result.FlaggedRighteous)
	}
	if len(result.FlaggedFixed) > 0 {
		t.Errorf("%s flagged its own #[Fixed] resolution, so the published good example still is the sin: %v", result.Detector, result.FlaggedFixed)
	}
	if len(result.Unexpected) > 0 {
		t.Errorf("%s flagged unmarked code (a false positive, or an unmarked sin): %v", result.Detector, result.Unexpected)
	}
}

// proveDiversity fails the test unless the detector fires on MinScenarios mutually diverse findings.
func (f Fixture) proveDiversity(t testing.TB, detector detectors.Detector) {
	t.Helper()
	scenarios, err := f.Scenarios(detector)
	if err != nil {
		t.Error(err)

		return
	}
	if largest := LargestDiverseGroup(scenarios); largest < MinScenarios {
		t.Errorf("%s: needs ≥%d mutually-DIVERSE scenarios (different files, <%.0f%% overlap) but the largest diverse group of its %d finding(s) is %d. Add genuinely different cases, not copies.",
			catalog.Name(detector), MinScenarios, MaxSimilarity, len(scenarios), largest)
	}
}

// Verify runs each detector over the fixture and sorts every finding: on a mark of its sin (hit), on
// its own fix, on its righteous twin, or on unmarked code. A sin mark nothing flagged is missed.
func (f Fixture) Verify() []Result {
	markers := Markers(f.Codebase)
	results := make([]Result, 0, len(f.Detectors))
	for _, detector := range f.Detectors {
		results = append(results, verify(f.Codebase, detector, markers))
	}

	return results
}

func verify(codebase *engine.Codebase, detector detectors.Detector, markers []Marker) Result {
	result := Result{Detector: catalog.Name(detector)}
	sinful := tagged(markers, Sinful, detector)
	fixed := tagged(markers, Fixed, detector)
	righteous := tagged(markers, Righteous, detector)
	hit := make([]bool, len(sinful))
	for _, finding := range detector.Find(codebase) {
		switch {
		case covering(fixed, finding) >= 0:
			result.FlaggedFixed = append(result.FlaggedFixed, finding.Location())
		case covering(righteous, finding) >= 0:
			result.FlaggedRighteous = append(result.FlaggedRighteous, finding.Location())
		case covering(sinful, finding) >= 0:
			hit[covering(sinful, finding)] = true
		default:
			result.Unexpected = append(result.Unexpected, finding.Location())
		}
	}
	for index, marker := range sinful {
		if !hit[index] {
			result.Missed = append(result.Missed, marker.Location)
		}
	}

	return result
}

// WithoutRighteous is every proven detector with no @righteous twin in the fixture.
func (f Fixture) WithoutRighteous() []string {
	markers := Markers(f.Codebase)
	var without []string
	for _, detector := range f.Detectors {
		if len(tagged(markers, Righteous, detector)) == 0 {
			without = append(without, catalog.Name(detector))
		}
	}

	return without
}

// UnknownResolutions is every #[Fixed] that names no known detector nor any published sin; a typo there
// silently drops the resolution.
func (f Fixture) UnknownResolutions() []string {
	known := f.Known
	if known == nil {
		known = detectors.All()
	}
	var names []string
	for _, detector := range known {
		names = append(names, keys(detector)...)
	}
	for _, sin := range sins.All() {
		names = append(names, catalog.Name(sin), sin.Definition().Name)
	}
	var unknown []string
	for _, marker := range Markers(f.Codebase) {
		if marker.Tag == Fixed && !marker.Names(names...) {
			unknown = append(unknown, fmt.Sprintf("#[Fixed(%s)] at %s names nothing in the catalog", marker.Name, marker.Location))
		}
	}

	return unknown
}

// Ambiguous is every file holding more than one class that carries both a sin mark and a fix for the
// same detector: the pairing of a fix to its sin there has two equally good answers.
func (f Fixture) Ambiguous() []string {
	markers := Markers(f.Codebase)
	var ambiguous []string
	for _, detector := range f.Detectors {
		halves := map[string]map[string]map[Tag]bool{}
		for _, marker := range append(tagged(markers, Sinful, detector), tagged(markers, Fixed, detector)...) {
			if halves[marker.File] == nil {
				halves[marker.File] = map[string]map[Tag]bool{}
			}
			if halves[marker.File][marker.Class] == nil {
				halves[marker.File][marker.Class] = map[Tag]bool{}
			}
			halves[marker.File][marker.Class][marker.Tag] = true
		}
		for file, classes := range halves {
			paired := 0
			for _, tags := range classes {
				if len(tags) == 2 {
					paired++
				}
			}
			if paired > 1 {
				ambiguous = append(ambiguous, fmt.Sprintf("%s has two scenarios in %s", catalog.Name(detector), file))
			}
		}
	}
	slices.Sort(ambiguous)

	return ambiguous
}

// keys are the names a marker may give a detector by: its own, its sin's type and its sin's name.
func keys(detector detectors.Detector) []string {
	return []string{catalog.Name(detector), catalog.Name(detector.Sin()), detector.Sin().Definition().Name}
}

// Marking are the markers with the tag that name the detector, by its own name, its sin's type or its sin's name.
func Marking(markers []Marker, tag Tag, detector detectors.Detector) []Marker {
	return tagged(markers, tag, detector)
}

// Naming says whether the marker names the detector, by any name a marker may give it.
func (m Marker) Naming(detector detectors.Detector) bool {
	return m.Names(keys(detector)...)
}

// tagged is every marker with the tag that names the detector.
func tagged(markers []Marker, tag Tag, detector detectors.Detector) []Marker {
	names := keys(detector)
	var found []Marker
	for _, marker := range markers {
		if marker.Tag == tag && marker.Names(names...) {
			found = append(found, marker)
		}
	}

	return found
}

// covering is the index of the first marker that covers the finding, -1 when none does.
func covering(markers []Marker, finding engine.Match) int {
	for index, marker := range markers {
		if marker.Covers(finding) {
			return index
		}
	}

	return -1
}
