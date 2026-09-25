package fixture

import (
	"strings"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// MinScenarios is how many mutually diverse findings a detector's fixture must hold.
const MinScenarios = 3

// MaxSimilarity is the percentage at which two findings count as copies of each other.
const MaxSimilarity = 60.0

// Scenario is one finding as the diversity check reads it: its file and the code around it.
type Scenario struct {
	File   string
	Source string
}

// Scenarios is each of the detector's findings, read as the function it sits in, or its type, or itself.
func Scenarios(codebase *engine.Codebase, detector detectors.Detector) ([]Scenario, error) {
	var scenarios []Scenario
	for _, finding := range detector.Find(codebase) {
		around := finding.EnclosingFunction()
		if !around.Exists() {
			around = finding.EnclosingType()
		}
		if !around.Exists() {
			around = finding
		}
		span, err := around.Span()
		if err != nil {
			return nil, err
		}
		scenarios = append(scenarios, Scenario{File: finding.File(), Source: span.Text()})
	}

	return scenarios, nil
}

// LargestDiverseGroup is the size of the largest group of scenarios that are pairwise diverse: in
// different files and under MaxSimilarity percent alike.
func LargestDiverseGroup(scenarios []Scenario) int {
	count := len(scenarios)
	diverse := make([][]bool, count)
	for i := range diverse {
		diverse[i] = make([]bool, count)
	}
	for i := 0; i < count; i++ {
		for j := i + 1; j < count; j++ {
			apart := scenarios[i].File != scenarios[j].File &&
				Similarity(scenarios[i].Source, scenarios[j].Source) < MaxSimilarity
			diverse[i][j], diverse[j][i] = apart, apart
		}
	}
	candidates := make([]int, count)
	for i := range candidates {
		candidates[i] = i
	}

	return growClique(candidates, 0, diverse)
}

// growClique is the largest clique reachable by adding candidates to one of the given size.
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

// Similarity is how alike two pieces of code are, in percent, whitespace runs folded: PHP's similar_text.
func Similarity(a, b string) float64 {
	first, second := fold(a), fold(b)
	if len(first)+len(second) == 0 {
		return 0
	}

	return float64(similar(first, second)) * 2 * 100 / float64(len(first)+len(second))
}

// fold trims the code and folds every whitespace run to one space.
func fold(code string) string {
	return strings.Join(strings.Fields(code), " ")
}

// similar counts the bytes two strings share, as PHP's php_similar_char does: the longest common
// run first, the earliest one on a tie, then the same on either side of it.
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
