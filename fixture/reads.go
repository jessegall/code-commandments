package fixture

import (
	"slices"
	"sort"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

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

func found(detector detectors.Detector, codebase *engine.Codebase) map[string]bool {
	locations := map[string]bool{}
	for _, finding := range detector.Find(codebase) {
		locations[finding.Location()] = true
	}

	return locations
}

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

func inFile(location, path string) bool {
	return len(location) > len(path) && location[:len(path)] == path && location[len(path)] == ':'
}

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

func (f Fixture) UnmarkedAcrossFiles() []string {
	var missing []string
	for _, name := range f.ReadsAcrossFiles() {
		if !slices.Contains(f.MarkedAcrossFiles(), name) {
			missing = append(missing, name)
		}
	}

	return missing
}
