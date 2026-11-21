package fixture

import (
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/rules"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// Fixture is a marked codebase and the detectors it proves.
type Fixture struct {
	Codebase  *engine.Codebase
	Detectors []Rule
	Known     []Rule
	Resolver  ScenarioResolver
}

func (f Fixture) Within(location string) string {
	return strings.TrimPrefix(location, f.folder()+"/")
}

func (f Fixture) folder() string {
	folder := ""
	for at, file := range f.Codebase.Files() {
		held := file.Path[:strings.LastIndex(file.Path, "/")+1]
		if at == 0 {
			folder = held

			continue
		}
		for folder != "" && !strings.HasPrefix(held, folder) {
			folder = folder[:strings.LastIndex(folder[:len(folder)-1], "/")+1]
		}
	}

	return strings.TrimSuffix(folder, "/")
}

// Result is how one detector fared against the fixture's markers.
type Result struct {
	Detector         string
	Missed           []string
	Unexpected       []string
	FlaggedRighteous []string
	FlaggedFixed     []string
}

func (r Result) Passed() bool {
	return len(r.Missed)+len(r.Unexpected)+len(r.FlaggedRighteous)+len(r.FlaggedFixed) == 0
}

const MinChainFiles = 5

func (f Fixture) ChainDepths() map[string]int {
	depths := map[string]int{}
	for _, detector := range f.Detectors {
		chained, ok := detector.(chained)
		if !ok {
			continue
		}
		deepest := 0
		for _, finding := range detector.Find(f.Codebase) {
			deepest = max(deepest, len(filesAlong(chained.ChainPath(finding, f.Codebase))))
		}
		depths[catalog.Name(detector)] = deepest
	}

	return depths
}

func filesAlong(steps []string) map[string]bool {
	files := map[string]bool{}
	for _, step := range steps {
		_, file, named := strings.Cut(step, "@")
		if !named {
			continue
		}
		files[file] = true
	}

	return files
}

const MinRecurrenceFiles = 2

func (f Fixture) RecurrenceSpans() map[string]int {
	spans := map[string]int{}
	for _, detector := range f.Detectors {
		recurring, ok := detector.(grouped)
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

func (f Fixture) Verify() []Result {
	markers := Markers(f.Codebase)
	results := make([]Result, 0, len(f.Detectors))
	for _, detector := range f.Detectors {
		results = append(results, verify(f.Codebase, detector, markers))
	}

	return results
}

func verify(codebase *engine.Codebase, detector Rule, markers []Marker) Result {
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

func (f Fixture) UnknownResolutions() []string {
	known := f.Known
	if known == nil {
		known = Proven(rules.All())
	}
	var names []string
	for _, detector := range known {
		names = append(names, keys(detector)...)
	}
	for _, sin := range rules.Sins() {
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
			if pairedIn(classes) > 1 {
				ambiguous = append(ambiguous, fmt.Sprintf("%s has two scenarios in %s", catalog.Name(detector), file))
			}
		}
	}
	slices.Sort(ambiguous)

	return ambiguous
}

func pairedIn(classes map[string]map[Tag]bool) int {
	paired := 0
	for _, tags := range classes {
		if len(tags) != 2 {
			continue
		}
		paired++
	}

	return paired
}

func keys(detector Rule) []string {
	return slices.Concat(catalog.Names(detector), catalog.Names(detector.Sin()), []string{detector.Sin().Definition().Name})
}

// Rule is anything a fixture proves: it names one sin and finds it.
type Rule interface {
	Sin() sins.Sin
	Find(codebase *engine.Codebase) []engine.Match
}

// grouped is a rule whose findings come in groups, each finding with the key of the group it belongs to.
type grouped interface {
	GroupKey(match engine.Match) (string, bool)
}

// chained is a rule whose finding reads a path of other places, each of which a fixture marks.
type chained interface {
	ChainPath(finding engine.Match, codebase *engine.Codebase) []string
}

func Marking(markers []Marker, tag Tag, detector Rule) []Marker {
	return tagged(markers, tag, detector)
}

func (m Marker) Naming(detector Rule) bool {
	return m.Names(keys(detector)...)
}

func tagged(markers []Marker, tag Tag, detector Rule) []Marker {
	names := keys(detector)
	var found []Marker
	for _, marker := range markers {
		if marker.Tag == tag && marker.Names(names...) {
			found = append(found, marker)
		}
	}

	return found
}

func covering(markers []Marker, finding engine.Match) int {
	for index, marker := range markers {
		if marker.Covers(finding) {
			return index
		}
	}

	return -1
}
