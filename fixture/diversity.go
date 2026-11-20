package fixture

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
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
		subject := finding
		if comment, ok := finding.AsComment(); ok && comment.Documents().Exists() {
			subject = comment.Documents()
		}
		scope := subject.DeclaringType()
		if subject.Is(engine.TypeDeclaration) {
			scope = subject
		}
		if subject.Is(engine.Import) {
			scope = subject.Root()
		}
		if !scope.Exists() {
			scope = subject.EnclosingFunction()
		}
		if !scope.Exists() {
			scope = subject
		}
		span, err := scope.Span()
		if err != nil {
			return nil, err
		}
		text := span.Lines()
		for _, method := range scope.Methods() {
			if method.EnclosingType().Identity() == scope.Identity() {
				continue
			}
			if body, err := method.Span(); err == nil {
				text += "\n" + body.Lines()
			}
		}
		scenarios = append(scenarios, Scenario{File: finding.File(), Source: text})
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
	return DiverseGroupUpTo(scenarios, len(scenarios))
}

func DiverseGroupUpTo(scenarios []Scenario, limit int) int {
	count := len(scenarios)
	diverse := make([][]bool, count)
	texts := make([]string, count)
	for i := range diverse {
		diverse[i] = make([]bool, count)
		texts[i] = fold(scenarios[i].Source)
	}
	for i := 0; i < count; i++ {
		for j := i + 1; j < count; j++ {
			apart := scenarios[i].File != scenarios[j].File && likeness(texts[i], texts[j]) < MaxSimilarity
			diverse[i][j], diverse[j][i] = apart, apart
		}
	}
	candidates := make([]int, count)
	for i := range candidates {
		candidates[i] = i
	}

	return growClique(candidates, 0, 0, limit, diverse)
}

func growClique(candidates []int, size, best, limit int, diverse [][]bool) int {
	best = max(size, best)
	for k, node := range candidates {
		if best >= limit || size+len(candidates)-k <= best {
			break
		}
		var rest []int
		for _, other := range candidates[k+1:] {
			if diverse[node][other] {
				rest = append(rest, other)
			}
		}
		best = growClique(rest, size+1, best, limit, diverse)
	}

	return best
}

func Likeness(a, b string) float64 {
	return likeness(fold(a), fold(b))
}

func likeness(a, b string) float64 {
	return min(similarity(a, b), similarity(b, a))
}

func Similarity(a, b string) float64 {
	return similarity(fold(a), fold(b))
}

func similarity(a, b string) float64 {
	if len(a)+len(b) == 0 {
		return 0
	}

	return float64(similar(a, b)) * 2 * 100 / float64(len(a)+len(b))
}

func fold(code string) string {
	lines := slices.DeleteFunc(strings.Split(code, "\n"), func(line string) bool {
		return prose.IsFixtureMarker(commentDelimiters.ReplaceAllString(strings.TrimSpace(line), ""))
	})
	return strings.Join(strings.Fields(strings.Join(lines, "\n")), " ")
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
