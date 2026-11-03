package fixture

import (
	"slices"
	"sort"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// ReadsAcrossFiles is every detector of the fixture whose answers in a file change when it is given that file
// alone instead of the whole fixture: it read a recurrence, a caller, a twin, a layer or what another engine
// published, and it needs the whole scan. It is MEASURED, by asking each detector the same question twice, so a
// scoped run's decision to read one file rather than the tree rests on what the rules really do.
//
// A detector that answers the same either way reads the file it reports in and nothing else, whatever its code
// looks like.
func (f Fixture) ReadsAcrossFiles() []string {
	whole := map[string]map[string]bool{}
	for _, detector := range f.Detectors {
		whole[catalog.Name(detector)] = found(detector, f.Codebase)
	}

	across := map[string]bool{}
	for _, file := range f.Codebase.Files() {
		alone := file.Alone()
		for _, detector := range f.Detectors {
			name := catalog.Name(detector)
			if across[name] {
				continue
			}

			if differs(found(detector, alone), whole[name], file.File.Path) {
				across[name] = true
			}
		}
	}

	named := make([]string, 0, len(across))
	for name := range across {
		named = append(named, name)
	}
	sort.Strings(named)

	return named
}

// found are the detector's findings by location, which is what two runs of it are compared by.
func found(detector detectors.Detector, codebase *engine.Codebase) map[string]bool {
	locations := map[string]bool{}
	for _, finding := range detector.Find(codebase) {
		locations[finding.Location()] = true
	}

	return locations
}

// differs says whether the two runs disagree about the file: a finding one of them has in that file and the other
// has not. Findings elsewhere are no disagreement, since a run of one file could not have them.
func differs(alone, whole map[string]bool, path string) bool {
	for location := range alone {
		if !whole[location] {
			return true
		}
	}

	for location := range whole {
		if inFile(location, path) && !alone[location] {
			return true
		}
	}

	return false
}

// inFile says whether the location lies in that file: a location is `path:line`, and a path may hold a colon on
// no platform the tool runs on.
func inFile(location, path string) bool {
	return len(location) > len(path) && location[:len(path)] == path && location[len(path)] == ':'
}

// MarkedAcrossFiles are the detectors of the fixture that carry the cross-file marker, named as a finding names
// them.
func (f Fixture) MarkedAcrossFiles() []string {
	var marked []string
	for _, detector := range f.Detectors {
		if catalog.IsCrossFile(detector) {
			marked = append(marked, catalog.Name(detector))
		}
	}
	sort.Strings(marked)

	return marked
}

// UnmarkedAcrossFiles are the detectors that read across files and do not say so, which is what makes a scoped
// run wrong: it would read one file and the rule would answer as though that were the whole program.
func (f Fixture) UnmarkedAcrossFiles() []string {
	var missing []string
	for _, name := range f.ReadsAcrossFiles() {
		if !slices.Contains(f.MarkedAcrossFiles(), name) {
			missing = append(missing, name)
		}
	}

	return missing
}
