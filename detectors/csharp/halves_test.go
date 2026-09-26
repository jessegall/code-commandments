package csharp_test

import (
	"maps"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
)

// TestJudgingTheFixtureInHalvesFindsWhatJudgingItWholeFinds judges the C# fixture as two parts, each read alone
// with the summary of both, the way a solution too large to hold is judged a project at a time, and holds every
// rule to what it finds in the fixture read whole. The rules that weigh candidates across the program decide over
// both parts' records with every match let go, so they decide from the records alone.
func TestJudgingTheFixtureInHalvesFindsWhatJudgingItWholeFinds(t *testing.T) {
	root, err := filepath.Abs("../../tests/Fixtures/csharp")
	if err != nil {
		t.Fatal(err)
	}
	stream, err := bridge.Once(bridge.TestRoslyn(t, root), root)
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
		var got []place
		if aggregating, isAggregating := detector.(detectors.Aggregating); isAggregating {
			var records []detectors.Candidate
			var at []place
			for _, part := range halves {
				for _, candidate := range aggregating.Candidates(part) {
					at = append(at, placed(detector, []engine.Match{candidate.At})...)
					records = append(records, detectors.Candidate{Record: candidate.Record})
				}
			}
			for _, chosen := range aggregating.Decide(records) {
				got = append(got, at[chosen])
			}
		} else {
			for _, part := range halves {
				got = append(got, placed(detector, detector.Find(part))...)
			}
		}
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

// placed is where each match stands, and the group the rule puts it in, read while its codebase is held.
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

// twins is, for each place in a group, the other places of its group, sorted.
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

// half is one of two parts of the stream, its files cut by the folder under the project they stand in.
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

// folderOf is the folder under Shop the file stands in.
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
