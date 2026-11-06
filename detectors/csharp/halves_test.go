package csharp_test

import (
	"maps"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
)

func TestJudgingTheFixtureInHalvesFindsWhatJudgingItWholeFinds(t *testing.T) {
	root, err := filepath.Abs("../../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridgetest.Roslyn(t, root), root)
	if err != nil {
		t.Fatal(err)
	}
	whole := engine.Load(stream)
	halves := []*engine.Codebase{engine.Load(half(stream, 0)), engine.Load(half(stream, 1))}
	merged := cs.Summarize(halves[0])
	merged.Merge(cs.Summarize(halves[1]))
	for _, part := range halves {
		cs.Judge(part, merged)
	}

	for _, detector := range detectors.Of(catalog.CSharp) {
		want := placed(detector, detector.Find(whole))
		got := foundInHalves(detector, halves)
		if found, whole := locations(got), locations(want); !slices.Equal(found, whole) {
			t.Errorf("%s finds %v in halves, %v whole", catalog.Name(detector), found, whole)
		}
		if found, whole := twins(got), twins(want); !maps.EqualFunc(found, whole, slices.Equal) {
			t.Errorf("%s names twins %v in halves, %v whole", catalog.Name(detector), found, whole)
		}
	}
}

// place is where a rule finds a sin, and the group its rule puts it in; none for a rule that groups nothing.
type place struct {
	at    string
	group string
}

func placed(detector detectors.Detector, matches []engine.Match) []place {
	grouped, groups := detector.(detectors.Grouped)
	var places []place
	for _, match := range matches {
		held := place{at: match.Location()}
		if groups && match.Exists() {
			if key, keyed := grouped.GroupKey(match); keyed {
				held.group = key
			}
		}
		places = append(places, held)
	}

	return places
}

func twins(places []place) map[string][]string {
	members := map[string][]string{}
	for _, held := range places {
		if held.group != "" {
			members[held.group] = append(members[held.group], held.at)
		}
	}
	named := map[string][]string{}
	for _, held := range places {
		if held.group == "" {
			continue
		}
		for _, member := range members[held.group] {
			if member != held.at {
				named[held.at] = append(named[held.at], member)
			}
		}
		slices.Sort(named[held.at])
	}

	return named
}

func half(stream *contract.Stream, which int) *contract.Stream {
	var folders []string
	for _, file := range stream.Files {
		if folder := folderOf(file.Path); !slices.Contains(folders, folder) {
			folders = append(folders, folder)
		}
	}
	slices.Sort(folders)
	part := &contract.Stream{Header: stream.Header, Program: stream.Program}
	for _, file := range stream.Files {
		if index := slices.Index(folders, folderOf(file.Path)); (index < len(folders)/2) == (which == 0) {
			part.Files = append(part.Files, file)
		}
	}
	part.Trailer = contract.Trailer{Files: len(part.Files)}

	return part
}

func folderOf(path string) string {
	_, under, _ := strings.Cut(path, "/Shop/")
	folder, _, _ := strings.Cut(under, "/")

	return folder
}

func locations(places []place) []string {
	var located []string
	for _, held := range places {
		located = append(located, held.at)
	}
	slices.Sort(located)

	return located
}

func foundInHalves(detector detectors.Detector, halves []*engine.Codebase) []place {
	var found []place
	aggregating, isAggregating := detector.(detectors.Aggregating)
	if !isAggregating {
		for _, part := range halves {
			found = append(found, placed(detector, detector.Find(part))...)
		}

		return found
	}

	var records []detectors.Candidate
	var at []place
	for _, part := range halves {
		for _, candidate := range aggregating.Candidates(part) {
			at = append(at, placed(detector, []engine.Match{candidate.At})...)
			records = append(records, detectors.Candidate{Record: candidate.Record})
		}
	}
	for _, chosen := range aggregating.Decide(records) {
		found = append(found, at[chosen])
	}

	return found
}
