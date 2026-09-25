package scribes

import (
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// Scribe rewrites the sins one detector found in the codebase.
type Scribe interface {
	Rewrite(findings []engine.Match, codebase *engine.Codebase) (Rewrites, error)
}

// Situated is a scribe that reads where the pass runs from before it rewrites: the project's own tools it may consult,
// and the source the pass does not hold.
type Situated interface {
	Situate(roots []string, sources Sources)
}

// Sources reads source the pass does not hold: text no file holds yet, or a file outside its roots.
type Sources interface {
	Parse(sources map[string]string) (*engine.Codebase, error)
	ReadFile(path string) (*engine.Codebase, error)
}

var scribesOf = map[string]func() Scribe{}

// ScribeFor is the scribe that rewrites the detector's sin.
func ScribeFor(detector detectors.Detector) (Scribe, bool) {
	scribe := scribesOf[catalog.Name(detector)]
	if scribe == nil {
		return nil, false
	}

	return scribe(), true
}

// Fixes enrols the scribe that rewrites a detector's sin, from the scribe's own file.
func Fixes(detector detectors.Detector, scribe func() Scribe) {
	scribesOf[catalog.Name(detector)] = scribe
}

// Steps is a step for each of the engine's detectors given whose sin a scribe rewrites, over the scanner, in the
// order the PHP tool discovers its detectors: by class name, a package's detectors under their namespace.
func Steps(engine catalog.Engine, scanner *Scanner, given []detectors.Detector) []Step {
	ofEngine := map[string]bool{}
	for _, detector := range detectors.Of(engine) {
		ofEngine[catalog.Name(detector)] = true
	}
	var fixable []detectors.Detector
	for _, detector := range given {
		_, repentable := detector.(detectors.Repentable)
		if repentable && ofEngine[catalog.Name(detector)] && scribesOf[catalog.Name(detector)] != nil {
			fixable = append(fixable, detector)
		}
	}
	slices.SortStableFunc(fixable, func(a, b detectors.Detector) int {
		return strings.Compare(className(engine, a), className(engine, b))
	})

	steps := make([]Step, 0, len(fixable))
	for _, detector := range fixable {
		steps = append(steps, DetectorStep{detector: detector, scribe: scribesOf[catalog.Name(detector)], scanner: scanner})
	}

	return steps
}

// className is the detector's PHP class name under its engine's namespace: its package's folders, capitalised,
// then its own name.
func className(engine catalog.Engine, detector detectors.Detector) string {
	kind := reflect.TypeOf(detector)
	for kind.Kind() == reflect.Pointer {
		kind = kind.Elem()
	}
	_, folder, _ := strings.Cut(kind.PkgPath(), "/detectors/"+string(engine))
	var name strings.Builder
	for _, segment := range strings.Split(strings.Trim(folder, "/"), "/") {
		if segment != "" {
			name.WriteString(strings.ToUpper(segment[:1]) + segment[1:] + `\`)
		}
	}

	return name.String() + kind.Name()
}

// DetectorStep runs one detector over the pass and hands the findings it may fix to the detector's scribe.
type DetectorStep struct {
	detector detectors.Detector
	scribe   func() Scribe
	scanner  *Scanner
}

// Name is the detector's name.
func (s DetectorStep) Name() string {
	return catalog.Name(s.detector)
}

// Stage is when the fix runs: last when it only reshapes what the other fixes settled, after the in-place fixes
// when its scribe says so, in place otherwise.
func (s DetectorStep) Stage() Stage {
	if _, last := s.detector.(detectors.RunsLast); last {
		return Normalising
	}
	if staged, ok := s.scribe().(Staged); ok {
		return staged.Stage()
	}

	return Fixing
}

// MatchesSin says whether the detector's sin answers to the query.
func (s DetectorStep) MatchesSin(query string) bool {
	return s.detector.Sin().Definition().Matches(query)
}

// Run rewrites the sins the detector finds in the files the pass may fix.
func (s DetectorStep) Run(pass Pass) (Rewrites, error) {
	codebase, err := s.scanner.Scan(pass)
	if err != nil {
		return Rewrites{}, err
	}
	var findings []engine.Match
	for _, finding := range s.detector.Find(codebase) {
		if pass.Fixes(finding.File()) {
			findings = append(findings, finding)
		}
	}

	scribe := s.scribe()
	if situated, ok := scribe.(Situated); ok {
		situated.Situate(pass.Roots, s.scanner)
	}

	return scribe.Rewrite(findings, codebase)
}

// Maintainer regenerates what a codebase declares about itself, whether or not any sin was found.
type Maintainer interface {
	Name() string
	Maintain(codebase *engine.Codebase, pass Pass) (Rewrites, error)
}

var maintainers = map[catalog.Engine][]Maintainer{}

// Maintains enrols a maintainer of one engine's files, from the maintainer's own file.
func Maintains(engine catalog.Engine, maintainer Maintainer) {
	maintainers[engine] = append(maintainers[engine], maintainer)
}

// MaintenanceSteps is a step for each maintainer of the engine's files, over the scanner, by name.
func MaintenanceSteps(engine catalog.Engine, scanner *Scanner) []Step {
	enrolled := slices.Clone(maintainers[engine])
	slices.SortFunc(enrolled, func(a, b Maintainer) int { return strings.Compare(a.Name(), b.Name()) })
	steps := make([]Step, 0, len(enrolled))
	for _, maintainer := range enrolled {
		steps = append(steps, MaintenanceStep{Maintainer: maintainer, Scanner: scanner})
	}

	return steps
}

// MaintenanceStep runs one maintainer over the pass, before every fix.
type MaintenanceStep struct {
	Maintainer Maintainer
	Scanner    *Scanner
}

// Name is the maintainer's name.
func (s MaintenanceStep) Name() string {
	return s.Maintainer.Name()
}

// Stage is first.
func (s MaintenanceStep) Stage() Stage {
	return Maintenance
}

// Run maintains the codebase under the pass.
func (s MaintenanceStep) Run(pass Pass) (Rewrites, error) {
	codebase, err := s.Scanner.Scan(pass)
	if err != nil {
		return Rewrites{}, err
	}

	return s.Maintainer.Maintain(codebase, pass)
}
