package fixture

import (
	"strings"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

const MinScenarios = 3

const MaxSimilarity = 60.0

// Scenario is one finding as the diversity check reads it: its file and the code around it.
type Scenario struct {
	File   string
	Source string
}

// ScenarioResolver reads a detector's findings as the scenarios the diversity check compares.
type ScenarioResolver func(codebase *engine.Codebase, detector detectors.Detector) ([]Scenario, error)

func (f Fixture) Scenarios(detector detectors.Detector) ([]Scenario, error) {
	resolve := f.Resolver
	if resolve == nil {
		resolve = ScopeScenarios
	}

	return resolve(f.Codebase, detector)
}

func ScopeScenarios(codebase *engine.Codebase, detector detectors.Detector) ([]Scenario, error) {
	var scenarios []Scenario
	chained, isChain := detector.(detectors.ChainDetector)
	for _, finding := range detector.Find(codebase) {
		if isChain {
			scenarios = append(scenarios, Scenario{File: finding.File(), Source: strings.Join(chained.ChainPath(finding, codebase), "\n")})

			continue
		}
		scope := finding.EnclosingType()
		if !scope.Exists() {
			scope = finding.EnclosingFunction()
		}
		if !scope.Exists() {
			scope = finding
		}
		span, err := scope.Span()
		if err != nil {
			return nil, err
		}
		scenarios = append(scenarios, Scenario{File: finding.File(), Source: span.Lines()})
	}

	return scenarios, nil
}

func FileScenarios(codebase *engine.Codebase, detector detectors.Detector) ([]Scenario, error) {
	var scenarios []Scenario
	for _, finding := range detector.Find(codebase) {
		source, err := finding.Source().Source()
		if err != nil {
			return nil, err
		}
		scenarios = append(scenarios, Scenario{File: finding.File(), Source: string(source)})
	}

	return scenarios, nil
}

func LargestDiverseGroup(scenarios []Scenario) int {
	count := len(scenarios)
	diverse := make([][]bool, count)
	for i := range diverse {
		diverse[i] = make([]bool, count)
	}
	for i := 0; i < count; i++ {
		for j := i + 1; j < count; j++ {
			apart := scenarios[i].File != scenarios[j].File &&
				Likeness(scenarios[i].Source, scenarios[j].Source) < MaxSimilarity
			diverse[i][j], diverse[j][i] = apart, apart
		}
	}
	candidates := make([]int, count)
	for i := range candidates {
		candidates[i] = i
	}

	return growClique(candidates, 0, diverse)
}

func growClique(candidates []int, size int, diverse [][]bool) int {
	best := size
	for k, node := range candidates {
		var rest []int
		for _, other := range candidates[k+1:] {
			if diverse[node][other] {
				rest = append(rest, other)
			}
		}
		best = max(best, growClique(rest, size+1, diverse))
	}

	return best
}

func Likeness(a, b string) float64 {
	return min(Similarity(a, b), Similarity(b, a))
}

func Similarity(a, b string) float64 {
	first, second := fold(a), fold(b)
	if len(first)+len(second) == 0 {
		return 0
	}

	return float64(similar(first, second)) * 2 * 100 / float64(len(first)+len(second))
}

func fold(code string) string {
	return strings.Join(strings.Fields(code), " ")
}

func similar(a, b string) int {
	at, bt, longest := 0, 0, 0
	for i := 0; i < len(a); i++ {
		for j := 0; j < len(b); j++ {
			run := 0
			for i+run < len(a) && j+run < len(b) && a[i+run] == b[j+run] {
				run++
			}
			if run > longest {
				at, bt, longest = i, j, run
			}
		}
	}
	if longest == 0 {
		return 0
	}
	shared := longest
	if at > 0 && bt > 0 {
		shared += similar(a[:at], b[:bt])
	}
	if at+longest < len(a) && bt+longest < len(b) {
		shared += similar(a[at+longest:], b[bt+longest:])
	}

	return shared
}
