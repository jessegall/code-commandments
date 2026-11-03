// Package fixturetest proves detectors against a self-checking fixture from a test: the harness's verdicts, read
// by fixture, reported through testing, which the shipped tool never imports.
package fixturetest

import (
	"slices"
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
		report(t, f, result)
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
	proveWhatEachRuleReads(t, f)
	for detector, widest := range f.RecurrenceSpans() {
		if widest < fixture.MinRecurrenceFiles {
			t.Errorf("%s is a recurrence detector but its widest group touches only %d file(s); mark one recurring group across two classes or files, not twice in one", detector, widest)
		}
	}
}

// proveWhatEachRuleReads holds every detector to saying whether it reads more than the file it reports in, which
// is what lets a scoped run read the files it judges and nothing else. It is measured, by asking each detector
// the same question of one file and of the whole fixture: a rule whose answers differ reads across files, and one
// that does not SAY so would answer a one-file scan as though it were the whole program.
func proveWhatEachRuleReads(t testing.TB, f fixture.Fixture) {
	t.Helper()

	across := f.ReadsAcrossFiles()
	for _, name := range across {
		if !slices.Contains(f.MarkedAcrossFiles(), name) {
			t.Errorf("%s answers differently for one file than for the whole fixture, so it reads across files: give it the method CrossFile(), or it will answer a scoped run as though one file were the whole program", name)
		}
	}

	// The other direction is told, not failed. "Its answers do not change on THIS fixture" is weaker than "it
	// never reads across files": a rule that compares routes across files still finds the fixture's duplicate
	// pair when both sit in one file. Taking the marker off on that evidence would starve the rule, where leaving
	// it on only costs a scoped run the scan it would have read anyway.
	for _, name := range f.MarkedAcrossFiles() {
		if !slices.Contains(across, name) {
			t.Logf("%s says it reads across files and answers the same for one file as for the whole fixture: either the marker is not needed, or the fixture never exercises what it reads across", name)
		}
	}
}

// report fails the test for each way a detector's findings missed its markers, one failure per place, each named
// as the fixture holds it: a verdict is read in a line, and a list of whole paths is read in none.
func report(t testing.TB, f fixture.Fixture, result fixture.Result) {
	t.Helper()
	for _, missed := range result.Missed {
		t.Errorf("%s missed the sin marked at %s", result.Detector, f.Within(missed))
	}
	for _, righteous := range result.FlaggedRighteous {
		t.Errorf("%s flagged its righteous twin at %s, code it must leave alone", result.Detector, f.Within(righteous))
	}
	for _, fixed := range result.FlaggedFixed {
		t.Errorf("%s flagged its own fixed resolution at %s, so the published good example still is the sin", result.Detector, f.Within(fixed))
	}
	for _, unexpected := range result.Unexpected {
		t.Errorf("%s flagged unmarked code at %s: a false positive, or a sin nobody marked", result.Detector, f.Within(unexpected))
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
