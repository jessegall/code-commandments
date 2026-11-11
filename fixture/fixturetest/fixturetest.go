// Package fixturetest proves detectors against a self-checking fixture from a test: the harness's verdicts, read by fixture, reported through testing, which the shipped tool never imports.
package fixturetest

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
)

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

func proveWhatEachRuleReads(t testing.TB, f fixture.Fixture) {
	t.Helper()

	across := f.ReadsAcrossFiles()
	for _, name := range across {
		if !slices.Contains(f.MarkedAcrossFiles(), name) {
			t.Errorf("%s answers differently for one file than for the whole fixture, so it reads across files: give it the method CrossFile(), or it will answer a scoped run as though one file were the whole program", name)
		}
	}

	for _, name := range f.MarkedAcrossFiles() {
		if !slices.Contains(across, name) {
			t.Logf("%s says it reads across files and answers the same for one file as for the whole fixture: either the marker is not needed, or the fixture never exercises what it reads across", name)
		}
	}
}

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

const mostARuleMayGrow = 3

func NoRuleOutgrowsItsTree(t testing.TB, small, large *engine.Codebase, rules []detectors.Detector) {
	t.Helper()

	if len(rules) == 0 {
		t.Fatal("no rule was handed in, so this test would pass by looking at nothing")
	}

	grown := fixture.Grown(small, large, rules)

	for _, each := range grown[:min(5, len(grown))] {
		t.Logf("%s cost %d points over the fixture and %d over the same fixture twice, %.1f times",
			each.Detector, each.Small, each.Large, each.Times)
	}

	for _, each := range grown {
		if each.Times > mostARuleMayGrow {
			t.Errorf("%s cost %d points over the fixture and %d over the same fixture twice, %.1f times, where "+
				"twice the tree may cost about twice the compute: a rule that grows faster than the project is one "+
				"a consumer cannot escape", each.Detector, each.Small, each.Large, each.Times)
		}
	}
}
