package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/detectors/shared"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// ConvertedArgumentDetector finds calls that convert what they hand one scalar parameter of a method of the codebase's own the same way, at half or more of the calls filling it: the conversion belongs in the method.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ConvertedArgumentDetector{})
}

func (ConvertedArgumentDetector) Sin() sins.Sin {
	return shared.SinOf(shared.ConvertedArgument{})
}

func (d ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

func (ConvertedArgumentDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	counts := map[string]int{}
	var order []string
	var candidates []detectors.Candidate
	for _, call := range codebase.Of(contract.CSharp).WhereCall().Get() {
		evidence := call.ConversionEvidence()
		for _, slot := range evidence.Slots {
			if counts[slot] == 0 {
				order = append(order, slot)
			}
			counts[slot]++
		}
		if evidence.Key != "" {
			candidates = append(candidates, detectors.Candidate{At: call, Record: keyed{key: evidence.Key, read: true}})
		}
	}
	return append(candidates, supplied(counts, order)...)
}
func (ConvertedArgumentDetector) Decide(candidates []detectors.Candidate) []int {
	counts, _ := suppliedIn(candidates)
	return engine.DominantConversionPositions(len(candidates), keyOf(candidates), counts)
}
func (ConvertedArgumentDetector) GroupKey(match engine.Match) (string, bool) {
	key := match.ConversionEvidence().Key
	return key, key != ""
}

func (ConvertedArgumentDetector) CrossFile() {}
