// Package fixturetest proves detectors against a self-checking fixture from a test: the harness's verdicts, read
// by fixture, reported through testing, which the shipped tool never imports.
package fixturetest

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/fixture"
)

// Prove fails the test unless every detector flags exactly its marked sins, leaves its righteous
// twin and its fixes alone, and fires on at least fixture.MinScenarios mutually diverse findings — and the
// fixture's own markers are sound.
func Prove(t testing.TB, f fixture.Fixture) {
	t.Helper()
	if len(f.Detectors) == 0 {
		t.Error("no detectors were verified against the fixture")
	}
	for _, result := range f.Verify() {
		report(t, result)
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
		proveDiversity(t, f, detector)
	}
	for detector, deepest := range f.ChainDepths() {
		if deepest < fixture.MinChainFiles {
			t.Errorf("%s is a chain detector but its deepest finding crosses only %d file(s); it must follow a value through %d or more", detector, deepest, fixture.MinChainFiles)
		}
	}
	for detector, widest := range f.RecurrenceSpans() {
		if widest < fixture.MinRecurrenceFiles {
			t.Errorf("%s is a recurrence detector but its widest group touches only %d file(s); mark one recurring group across two classes or files, not twice in one", detector, widest)
		}
	}
}

// report fails the test for each way a detector's findings missed its markers.
func report(t testing.TB, result fixture.Result) {
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
func proveDiversity(t testing.TB, f fixture.Fixture, detector detectors.Detector) {
	t.Helper()
	scenarios, err := f.Scenarios(detector)
	if err != nil {
		t.Error(err)

		return
	}
	if largest := fixture.LargestDiverseGroup(scenarios); largest < fixture.MinScenarios {
		t.Errorf("%s: needs ≥%d mutually-DIVERSE scenarios (different files, <%.0f%% overlap) but the largest diverse group of its %d finding(s) is %d. Add genuinely different cases, not copies.",
			catalog.Name(detector), fixture.MinScenarios, fixture.MaxSimilarity, len(scenarios), largest)
	}
}
