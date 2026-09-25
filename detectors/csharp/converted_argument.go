package csharp

import (
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ConvertedArgumentDetector finds calls that convert what they hand one scalar parameter of a method of the codebase's own the same way, at half or more of the calls filling it: the conversion belongs in the method.
type ConvertedArgumentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ConvertedArgumentDetector{})
}

// Sin is the sin the detector finds.
func (ConvertedArgumentDetector) Sin() sins.Sin {
	return cssins.ConvertedArgument{}
}

// Find is every place the sin is committed.
func (d ConvertedArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return detectors.Aggregate(d, codebase)
}

// Candidates is every call that converts a scalar for a parameter of the codebase's own method, keyed by the
// parameter and the conversion, beside how many calls fill each parameter.
func (ConvertedArgumentDetector) Candidates(codebase *engine.Codebase) []detectors.Candidate {
	program := cs.In(codebase).Program
	counts := map[string]int{}
	var order []string
	var candidates []detectors.Candidate
	for _, match := range cs.In(codebase).WhereCall().Get() {
		call := cs.Node{Match: match}
		for position := range call.Arguments() {
			slot := call.Target().Symbol() + "#" + strconv.Itoa(position)
			if counts[slot] == 0 {
				order = append(order, slot)
			}
			counts[slot]++
		}
		if slot, converts := conversionSlot(call, program); converts {
			candidates = append(candidates, detectors.Candidate{At: match, Record: keyed{key: slot, read: true}})
		}
	}

	return append(candidates, supplied(counts, order)...)
}

// Decide is every call converting as half or more of the calls filling its parameter do.
func (ConvertedArgumentDetector) Decide(candidates []detectors.Candidate) []int {
	counts, _ := suppliedIn(candidates)
	var dominant []int
	for _, bucket := range engine.Recurring(len(candidates), keyOf(candidates), 2) {
		slot := candidates[bucket[0]].Record.(keyed).key
		if float64(len(bucket))/float64(counts[slot[:strings.LastIndex(slot, "=")]]) >= 0.5 {
			dominant = append(dominant, bucket...)
		}
	}

	return dominant
}

// GroupKey is the group a call recurs in: the parameter of the codebase's own method it converts a scalar for, and
// the conversion.
func (ConvertedArgumentDetector) GroupKey(match engine.Match) (string, bool) {
	return conversionSlot(cs.Node{Match: match}, cs.Of(match.Codebase()))
}

// conversionSlot is the first parameter of the codebase's own method the call converts a scalar for, with the
// conversion: `Shop.Pricing.Quote(…)#1=Convert.ToInt32`; false for a call that converts none.
func conversionSlot(call cs.Node, program *cs.Program) (string, bool) {
	if !program.ReachesOwnSignature(call) {
		return "", false
	}
	conversions := call.ScalarConversions()
	var positions []int
	for position := range conversions {
		positions = append(positions, position)
	}
	if len(positions) == 0 {
		return "", false
	}
	sort.Ints(positions)

	return call.Target().Symbol() + "#" + strconv.Itoa(positions[0]) + "=" + conversions[positions[0]], true
}
